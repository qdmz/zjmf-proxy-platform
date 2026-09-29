<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Services\BillingService;

class ShopController extends Controller
{
    /** 上游配置项类型 → 前端展示类型 */
    public static function optionKind(int $type): string
    {
        if (in_array($type, [4, 7, 9, 11, 14], true)) {
            return 'qty';
        }
        if ($type === 3) {
            return 'yesno';
        }
        return 'select';
    }

    public function index(): string
    {
        $type = $_GET['type'] ?? '';
        $where = "p.`status` = 1";
        $params = [];
        if ($type !== '') {
            $where .= " AND p.`type` = ?";
            $params[] = $type;
        }
        $products = DB::all(
            "SELECT p.*,
                    (SELECT MIN(price) FROM `product_prices` WHERE `product_id` = p.id) AS min_price
             FROM `products` p WHERE {$where}
             ORDER BY p.`sort` DESC, p.`id` DESC",
            $params
        );
        return $this->view('shop/index', [
            'title' => '产品选购',
            'products' => $products,
            'type' => $type,
            'user' => Auth::user(),
        ]);
    }

    public function detail(string $id): string
    {
        $product = DB::get("SELECT * FROM `products` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$product || (int)$product['status'] !== 1) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '产品不存在']);
        }
        $prices = DB::all("SELECT * FROM `product_prices` WHERE `product_id` = ? ORDER BY `price`", [(int)$id]);
        if (!$prices) {
            return $this->view('errors/404', ['title' => '产品暂无价格']);
        }
        $defaultCycle = $prices[0]['billingcycle'];
        $options = DB::all("SELECT * FROM `product_config_options` WHERE `product_id` = ? ORDER BY `sort`,`id`", [(int)$id]);
        foreach ($options as &$opt) {
            $opt['kind'] = self::optionKind((int)$opt['option_type']);
            $opt['subs'] = DB::all("SELECT * FROM `product_config_subs` WHERE `option_id` = ? ORDER BY `sort`,`id`", [$opt['id']]);
            foreach ($opt['subs'] as &$sub) {
                $pj = json_decode($sub['price_json'] ?? '{}', true) ?: [];
                $sub['display_price'] = (float)($pj[$defaultCycle] ?? $pj['*'] ?? 0);
            }
            unset($sub);
        }
        unset($opt);
        return $this->view('shop/detail', [
            'title' => $product['name'],
            'product' => $product,
            'prices' => $prices,
            'defaultCycle' => $defaultCycle,
            'options' => $options,
            'user' => Auth::user(),
        ]);
    }

    /** AJAX 实时计价 */
    public function quote(): void
    {
        $productId = (int)($_POST['product_id'] ?? 0);
        $cycle = $_POST['billingcycle'] ?? 'monthly';
        $configoption = $_POST['configoption'] ?? [];
        if (!is_array($configoption)) {
            $decoded = json_decode((string)$configoption, true);
            $configoption = is_array($decoded) ? $decoded : [];
        }
        $result = BillingService::quote($productId, $cycle, $configoption);
        if (!$result['ok']) {
            $this->fail($result['msg']);
        }
        $this->json(['total' => $result['total'], 'detail' => $result['detail']]);
    }
}
