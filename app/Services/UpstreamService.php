<?php
namespace App\Services;

use App\Core\DB;
use App\Core\Logger;
use App\Services\Upstream\ZjmfV1Client;

/**
 * 上游供货商管理 + 产品同步
 */
class UpstreamService
{
    /** 为供货商构建 API 客户端（含 JWT 缓存与持久化） */
    public static function client(array $provider): ZjmfV1Client
    {
        $client = new ZjmfV1Client(
            $provider['base_url'],
            $provider['account'],
            dec_data($provider['password_enc'])
        );
        $pid = (int) $provider['id'];
        $client->setToken(
            $provider['jwt'] ?: null,
            (int) $provider['jwt_expire'],
            function (string $jwt, int $expire) use ($pid) {
                DB::update('upstream_providers', ['jwt' => $jwt, 'jwt_expire' => $expire], '`id` = :id', ['id' => $pid]);
            }
        );
        return $client;
    }

    public static function get(int $id)
    {
        return DB::get("SELECT * FROM `upstream_providers` WHERE `id` = ? LIMIT 1", [$id]);
    }

    /** 测试连接：登录 + 拉取一条商品 */
    public static function testConnection(array $provider): array
    {
        $client = self::client($provider);
        $lr = $client->login(true);
        if (!$lr['ok']) {
            return $lr;
        }
        $resp = $client->getProducts(['limit' => 1]);
        if ((int)($resp['status'] ?? 0) === 200) {
            return ['ok' => true, 'msg' => '连接成功，API 登录与商品接口均正常'];
        }
        return ['ok' => false, 'msg' => '登录成功但商品接口异常: ' . ($resp['msg'] ?? '未知错误')];
    }

    /**
     * 同步上游产品到本地（幂等）
     * @return array ['ok'=>bool,'msg'=>string,'count'=>int]
     */
    public static function syncProducts(int $providerId): array
    {
        $provider = self::get($providerId);
        if (!$provider) {
            return ['ok' => false, 'msg' => '供货商不存在'];
        }
        $client = self::client($provider);
        $resp = $client->getProducts();
        if ((int)($resp['status'] ?? 0) !== 200) {
            Logger::upstream($providerId, 0, 'sync_products', [], $resp, false);
            return ['ok' => false, 'msg' => '拉取上游产品失败: ' . ($resp['msg'] ?? '未知错误')];
        }
        $groups = $resp['data']['first_group'] ?? [];
        $count = 0;
        foreach ($groups as $fg) {
            foreach ($fg['group'] ?? [] as $g) {
                foreach ($g['product'] ?? [] as $p) {
                    self::syncOneProduct($provider, $client, $p);
                    $count++;
                }
            }
        }
        DB::update('upstream_providers', ['last_sync_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => $providerId]);
        Logger::upstream($providerId, 0, 'sync_products', ['count' => $count], ['ok' => true], true);
        return ['ok' => true, 'msg' => "同步完成，共 {$count} 个产品", 'count' => $count];
    }

    /** 同步单个产品：基本信息 + 周期价格 + 配置选项 */
    protected static function syncOneProduct(array $provider, ZjmfV1Client $client, array $p): void
    {
        $providerId = (int) $provider['id'];
        $pid = (int) ($p['id'] ?? 0);
        if ($pid <= 0) {
            return;
        }
        $detail = $client->getProductConfig($pid);
        $d = ((int)($detail['status'] ?? 0) === 200) ? ($detail['data'] ?? []) : [];

        $cycles = [];
        $prices = [];
        foreach ($d['cycle'] ?? [] as $c) {
            $bc = $c['billingcycle'] ?? '';
            if (!$bc) continue;
            $cycles[] = $bc;
            $prices[$bc] = [
                'upstream_price' => (float)($c['product_price'] ?? 0),
                'upstream_setup_fee' => (float)($c['setup_fee'] ?? 0),
            ];
        }
        // 兼容列表接口自带的价格字段
        if (!$cycles && !empty($p['billingcycle'])) {
            $cycles = is_array($p['billingcycle']) ? $p['billingcycle'] : [$p['billingcycle']];
        }

        $exist = DB::get(
            "SELECT * FROM `products` WHERE `provider_id` = ? AND `upstream_pid` = ? LIMIT 1",
            [$providerId, $pid]
        );
        $data = [
            'provider_id' => $providerId,
            'upstream_pid' => $pid,
            'type' => $p['type'] ?? 'cloud',
            'name' => $p['name'] ?? ('产品' . $pid),
            'description' => $p['description'] ?? '',
            'billingcycles' => json_encode(array_values(array_unique($cycles)), JSON_UNESCAPED_UNICODE),
            'stock_control' => (int)($p['stock_control'] ?? 0),
            'stock_qty' => (int)($p['qty'] ?? 0),
            'upstream_updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($exist) {
            // 保留本地的上下架状态与加价设置
            unset($data['status']);
            DB::update('products', $data, '`id` = :id', ['id' => $exist['id']]);
            $productId = (int) $exist['id'];
            $markupType = $exist['markup_type'];
            $markupValue = (float) $exist['markup_value'];
        } else {
            $data['status'] = 0; // 新同步产品默认下架，管理员审核后上架
            $productId = (int) DB::insert('products', $data);
            $markupType = 'percent';
            $markupValue = 120.00;
        }

        // 周期价格（应用加价）
        foreach ($prices as $bc => $pr) {
            $sale = BillingService::applyMarkup($pr['upstream_price'], $markupType, $markupValue);
            $saleSetup = BillingService::applyMarkup($pr['upstream_setup_fee'], $markupType, $markupValue);
            $epr = DB::get(
                "SELECT * FROM `product_prices` WHERE `product_id` = ? AND `billingcycle` = ? LIMIT 1",
                [$productId, $bc]
            );
            $pdata = [
                'upstream_price' => $pr['upstream_price'],
                'upstream_setup_fee' => $pr['upstream_setup_fee'],
                'price' => $sale,
                'setup_fee' => $saleSetup,
            ];
            if ($epr) {
                // 若管理员手动改过销售价则保留，只更新上游价
                unset($pdata['price'], $pdata['setup_fee']);
                DB::update('product_prices', $pdata, '`id` = :id', ['id' => $epr['id']]);
            } else {
                $pdata['product_id'] = $productId;
                $pdata['billingcycle'] = $bc;
                DB::insert('product_prices', $pdata);
            }
        }

        // 配置选项
        foreach ($d['configoptions'] ?? [] as $opt) {
            $oid = (int)($opt['id'] ?? 0);
            if ($oid <= 0) continue;
            $eopt = DB::get(
                "SELECT * FROM `product_config_options` WHERE `product_id` = ? AND `upstream_option_id` = ? LIMIT 1",
                [$productId, $oid]
            );
            $odata = [
                'name' => $opt['name'] ?? ('选项' . $oid),
                'option_type' => (int)($opt['type'] ?? 1),
                'qty_min' => (int)($opt['qty_minimum'] ?? $opt['qty_min'] ?? 1),
                'qty_max' => (int)($opt['qty_maximum'] ?? $opt['qty_max'] ?? 1),
                'unit' => $opt['unit'] ?? '',
            ];
            if ($eopt) {
                DB::update('product_config_options', $odata, '`id` = :id', ['id' => $eopt['id']]);
                $optionId = (int) $eopt['id'];
            } else {
                $odata['product_id'] = $productId;
                $odata['upstream_option_id'] = $oid;
                $optionId = (int) DB::insert('product_config_options', $odata);
            }
            foreach ($opt['sub'] ?? [] as $sub) {
                $sid = (int)($sub['id'] ?? 0);
                if ($sid <= 0) continue;
                $upJson = [];
                $saleJson = [];
                foreach (($sub['pricing'] ?? []) as $sp) {
                    $bc = $sp['billingcycle'] ?? '';
                    if ($bc) {
                        $upJson[$bc] = (float)($sp['price'] ?? 0);
                        $saleJson[$bc] = BillingService::applyMarkup((float)($sp['price'] ?? 0), $markupType, $markupValue);
                    }
                }
                // 兼容扁平价格字段
                if (!$upJson && isset($sub['price'])) {
                    $upJson = ['*'=> (float)$sub['price']];
                    $saleJson = ['*'=> BillingService::applyMarkup((float)$sub['price'], $markupType, $markupValue)];
                }
                $esub = DB::get(
                    "SELECT * FROM `product_config_subs` WHERE `option_id` = ? AND `upstream_sub_id` = ? LIMIT 1",
                    [$optionId, $sid]
                );
                $sdata = [
                    'option_name' => $sub['option_name'] ?? $sub['name'] ?? ('子项' . $sid),
                    'upstream_price_json' => json_encode($upJson, JSON_UNESCAPED_UNICODE),
                    'price_json' => json_encode($saleJson, JSON_UNESCAPED_UNICODE),
                ];
                if ($esub) {
                    DB::update('product_config_subs', $sdata, '`id` = :id', ['id' => $esub['id']]);
                } else {
                    $sdata['option_id'] = $optionId;
                    $sdata['upstream_sub_id'] = $sid;
                    DB::insert('product_config_subs', $sdata);
                }
            }
        }
    }

    /** 重新应用加价到某个产品的所有价格 */
    public static function reapplyMarkup(int $productId): void
    {
        $p = DB::get("SELECT * FROM `products` WHERE `id` = ? LIMIT 1", [$productId]);
        if (!$p) return;
        foreach (DB::all("SELECT * FROM `product_prices` WHERE `product_id` = ?", [$productId]) as $pr) {
            DB::update('product_prices', [
                'price' => BillingService::applyMarkup((float)$pr['upstream_price'], $p['markup_type'], (float)$p['markup_value']),
                'setup_fee' => BillingService::applyMarkup((float)$pr['upstream_setup_fee'], $p['markup_type'], (float)$p['markup_value']),
            ], '`id` = :id', ['id' => $pr['id']]);
        }
    }
}
