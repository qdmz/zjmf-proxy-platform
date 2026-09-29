<?php
namespace App\Services\Upstream;

use App\Core\Http;

/**
 * 智简魔方财务系统 v1 开放 API 客户端
 *
 * 鉴权：POST /v1/login_api (form: account/password) → {status:200, jwt}
 * 后续请求头：authorization: JWT <token>（JWT 后有空格），token 有效期约 2 小时
 * 统一响应：{status, msg, data}，200=成功
 *
 * 覆盖：商品同步 / 实时计价 / 下单开通 / 余额支付 / 实例管理 / 续费退订
 */
class ZjmfV1Client
{
    private string $baseUrl;
    private string $account;
    private string $password;
    private ?string $jwt = null;
    private int $jwtExpire = 0;
    /** @var callable|null token 持久化回调 function(string $jwt, int $expire) */
    private $persistToken;

    public function __construct(string $baseUrl, string $account, string $password)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->account = $account;
        $this->password = $password;
    }

    public function setToken(?string $jwt, int $expire, ?callable $persistToken = null): void
    {
        $this->jwt = $jwt;
        $this->jwtExpire = $expire;
        $this->persistToken = $persistToken;
    }

    /** 登录获取 JWT，返回 [ok, jwt|msg] */
    public function login(bool $force = false): array
    {
        if (!$force && $this->jwt && $this->jwtExpire > time() + 300) {
            return ['ok' => true, 'jwt' => $this->jwt];
        }
        $res = Http::request('POST', $this->baseUrl . '/v1/login_api', [
            'account' => $this->account,
            'password' => $this->password,
        ], [], 20);
        $data = Http::json($res);
        if ($data === null) {
            return ['ok' => false, 'msg' => '上游无响应: ' . ($res['error'] ?: 'HTTP ' . $res['http_code'])];
        }
        $jwt = $data['jwt'] ?? $data['data']['jwt'] ?? $data['data']['token'] ?? null;
        if ((int)($data['status'] ?? 0) === 200 && $jwt) {
            $this->jwt = $jwt;
            $this->jwtExpire = time() + 6900; // 约2小时，提前续
            if ($this->persistToken) {
                call_user_func($this->persistToken, $jwt, $this->jwtExpire);
            }
            return ['ok' => true, 'jwt' => $jwt];
        }
        return ['ok' => false, 'msg' => '上游登录失败: ' . ($data['msg'] ?? '未知错误')];
    }

    /**
     * 通用 API 调用
     * @return array 原始响应数组 ['status'=>..,'msg'=>..,'data'=>..]
     */
    public function api(string $method, string $path, array $data = [], bool $retry = true): array
    {
        $lr = $this->login();
        if (!$lr['ok']) {
            return ['status' => 400, 'msg' => $lr['msg'], 'data' => null];
        }
        $headers = ['authorization: JWT ' . $this->jwt];
        $res = Http::request($method, $this->baseUrl . $path, $data, $headers, 30);
        $json = Http::json($res);
        if ($json === null) {
            return ['status' => 400, 'msg' => '上游返回非 JSON: ' . ($res['error'] ?: mb_substr($res['body'], 0, 200)), 'data' => null];
        }
        $status = (int)($json['status'] ?? 0);
        // token 失效则重新登录重试一次
        if (in_array($status, [401, 405, 1002], true) && $retry) {
            $lr = $this->login(true);
            if ($lr['ok']) {
                return $this->api($method, $path, $data, false);
            }
        }
        return $json;
    }

    public function ok(array $resp): bool
    {
        return (int)($resp['status'] ?? 0) === 200;
    }

    // ---------------- 商品 ----------------

    /** 商品列表：$params 可含 first_group_id / group_id / product_id */
    public function getProducts(array $params = []): array
    {
        return $this->api('GET', '/v1/products', $params);
    }

    /**
     * 魔方 V10 商品列表（公开接口，无需鉴权）
     * GET /api/product/list → {status:200, data:{list:[...]}}
     * 产品为扁平结构，字段: id/type/gid/name/description/product_price|price/billingcycle/qty
     */
    public function getProductsV10(array $params = []): array
    {
        $res = Http::request('GET', $this->baseUrl . '/api/product/list', $params, [], 30);
        $json = Http::json($res);
        if ($json === null) {
            return ['status' => 400, 'msg' => '上游返回非 JSON: ' . ($res['error'] ?: mb_substr($res['body'], 0, 200)), 'data' => null];
        }
        return $json;
    }

    /** 商品详情（含可配置选项、自定义字段、周期价格） */
    public function getProductConfig(int $productId): array
    {
        return $this->api('GET', '/v1/productsconfig', ['product_id' => $productId]);
    }

    /** 实时计价 */
    public function quote(int $productId, string $billingcycle, int $qty = 1, array $configoption = []): array
    {
        return $this->api('POST', '/v1/products/total', [
            'product_id' => $productId,
            'billingcycle' => $billingcycle,
            'qty' => $qty,
            'configoption' => $configoption,
        ]);
    }

    // ---------------- 下单开通 ----------------

    /** 加入购物车 */
    public function cartAdd(array $params): array
    {
        return $this->api('POST', '/v1/cart/products', $params);
    }

    /** 结算购物车 → 生成账单；免费订单直接返回 status=1001 + hostid */
    public function cartCheckout(string $payment = '', array $position = [0]): array
    {
        $params = ['position' => $position];
        if ($payment !== '') {
            $params['payment'] = $payment;
        }
        return $this->api('POST', '/v1/cart/checkout', $params);
    }

    /** 查询上游产品库存：GET /v1/cart/stock_control?pid=上游产品ID */
    public function cartStockControl(int $upstreamPid): array
    {
        return $this->api('GET', '/v1/cart/stock_control', ['pid' => $upstreamPid]);
    }

    /** 余额支付账单 → status=1001 + hostid 表示开通成功 */
    public function invoiceFund(int $invoiceId): array
    {
        return $this->api('POST', '/v1/invoices/' . $invoiceId . '/fund', ['id' => $invoiceId]);
    }

    /** 账单支付状态轮询：1000=已支付 */
    public function invoiceStatus(int $invoiceId): array
    {
        return $this->api('GET', '/v1/invoices/' . $invoiceId . '/status');
    }

    public function invoiceDetail(int $invoiceId): array
    {
        return $this->api('GET', '/v1/invoices/' . $invoiceId);
    }

    // ---------------- 实例管理 ----------------

    public function getHosts(array $params = []): array
    {
        return $this->api('GET', '/v1/hosts', $params);
    }

    public function getHost(int $hostId): array
    {
        return $this->api('GET', '/v1/hosts/' . $hostId);
    }

    /** 实例能力按钮清单（决定前台展示哪些操作按钮） */
    public function getHostModule(int $hostId): array
    {
        return $this->api('GET', '/v1/hosts/' . $hostId . '/module');
    }

    /**
     * 电源/管理操作（PUT 无 body）
     * func: on/off/reboot/hard_off/hard_reboot/repassword/reinstall/rescue/vnc/status
     */
    public function moduleAction(int $hostId, string $func, array $data = []): array
    {
        $method = in_array($func, ['reinstall', 'repassword', 'rescue'], true) ? 'PUT' : 'PUT';
        // vnc 为 PUT 取 url；status 为 GET
        if ($func === 'status') {
            return $this->api('GET', '/v1/hosts/' . $hostId . '/module/status', $data);
        }
        return $this->api($method, '/v1/hosts/' . $hostId . '/module/' . $func, $data);
    }

    /** 可重装系统列表 */
    public function getReinstallOs(int $hostId): array
    {
        return $this->api('GET', '/v1/hosts/' . $hostId . '/module/reinstall');
    }

    /** 电源状态 / 重装进度 */
    public function getModuleStatus(int $hostId, string $type = 'host'): array
    {
        return $this->api('GET', '/v1/hosts/' . $hostId . '/module/status', ['type' => $type]);
    }

    /** 流量图 */
    public function getCharts(int $hostId, string $type, int $start, int $end): array
    {
        return $this->api('GET', '/v1/hosts/' . $hostId . '/module/charts', [
            'type' => $type, 'start' => $start, 'end' => $end,
        ]);
    }

    // ---------------- 续费 / 退订 / 升降级 ----------------

    /** 生成续费账单 → invoiceid */
    public function renew(int $hostId, string $billingcycle): array
    {
        return $this->api('POST', '/v1/hosts/' . $hostId . '/renew', ['billingcycle' => $billingcycle]);
    }

    /** 自动续费开关 */
    public function setAutoRenew(int $hostId, int $on): array
    {
        return $this->api('PUT', '/v1/hosts/' . $hostId . '/renew', ['initiative_renew' => $on ? 1 : 0]);
    }

    public function getCancel(int $hostId): array
    {
        return $this->api('GET', '/v1/hosts/' . $hostId . '/cancel');
    }

    /** 退订请求：Immediate 立即 / Endofbilling 到期 */
    public function cancel(int $hostId, string $type, string $reason = ''): array
    {
        return $this->api('POST', '/v1/hosts/' . $hostId . '/cancel', [
            'type' => $type, 'reason' => $reason,
        ]);
    }

    public function deleteCancel(int $hostId): array
    {
        return $this->api('DELETE', '/v1/hosts/' . $hostId . '/cancel');
    }

    /** 配置项升降级预览/下单 */
    public function upgradeConfig(int $hostId, string $method, array $data = []): array
    {
        return $this->api($method, '/v1/hosts/' . $hostId . '/actions/upgradeconfig', $data);
    }

    public function upgradeConfigCheckout(int $hostId): array
    {
        return $this->api('POST', '/v1/hosts/' . $hostId . '/actions/upgradeconfig/checkout');
    }
}
