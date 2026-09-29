<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Services\UpstreamService;

class ProductController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $providerId = (int)($_GET['provider_id'] ?? 0);
        $status = $_GET['status'] ?? '';
        $kw = trim($_GET['kw'] ?? '');
        $where = '1=1';
        $params = [];
        if ($providerId > 0) { $where .= ' AND p.provider_id = ?'; $params[] = $providerId; }
        if ($status !== '') { $where .= ' AND p.status = ?'; $params[] = (int)$status; }
        if ($kw !== '') { $where .= ' AND p.name LIKE ?'; $params[] = '%' . $kw . '%'; }

        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `products` p WHERE {$where}", $params);
        $products = DB::all(
            "SELECT p.*, up.name AS provider_name,
                    (SELECT MIN(price) FROM `product_prices` WHERE `product_id` = p.id) AS min_price
             FROM `products` p LEFT JOIN `upstream_providers` up ON up.id = p.provider_id
             WHERE {$where} ORDER BY p.`sort` DESC, p.`id` DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per, ($page - 1) * $per])
        );
        $providers = DB::all("SELECT `id`,`name` FROM `upstream_providers` ORDER BY `id`");
        return $this->view('admin/product_index', [
            'title' => '产品管理', 'products' => $products, 'providers' => $providers,
            'filter' => ['provider_id' => $providerId, 'status' => $status, 'kw' => $kw],
            'pagination' => paginate($total, $page, $per, '/admin/products?' . http_build_query(array_filter(['provider_id' => $providerId, 'status' => $status, 'kw' => $kw], fn($v) => $v !== '' && $v !== 0))),
            'user' => $admin,
        ], 'layout_admin');
    }

    public function edit(string $id): string
    {
        $admin = $this->requireAdmin();
        $product = DB::get("SELECT * FROM `products` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$product) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '产品不存在'], 'layout_admin');
        }
        $prices = DB::all("SELECT * FROM `product_prices` WHERE `product_id` = ? ORDER BY `price`", [(int)$id]);
        return $this->view('admin/product_form', [
            'title' => '编辑产品', 'product' => $product, 'prices' => $prices, 'user' => $admin,
        ], 'layout_admin');
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $product = DB::get("SELECT * FROM `products` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$product) {
            redirect('/admin/products');
        }
        $data = [
            'name' => trim($_POST['name'] ?? $product['name']),
            'description' => trim($_POST['description'] ?? ''),
            'status' => (int)($_POST['status'] ?? 0),
            'sort' => (int)($_POST['sort'] ?? 0),
            'stock_control' => (int)($_POST['stock_control'] ?? 0),
            'stock_qty' => (int)($_POST['stock_qty'] ?? 0),
            'markup_type' => in_array($_POST['markup_type'] ?? '', ['percent', 'fixed'], true) ? $_POST['markup_type'] : 'percent',
            'markup_value' => round((float)($_POST['markup_value'] ?? 120), 2),
        ];
        DB::update('products', $data, '`id` = :id', ['id' => (int)$id]);

        // 周期销售价手动调整
        $prices = $_POST['prices'] ?? [];
        foreach ($prices as $priceId => $row) {
            DB::update('product_prices', [
                'price' => round((float)($row['price'] ?? 0), 2),
                'setup_fee' => round((float)($row['setup_fee'] ?? 0), 2),
                'sale_price' => round((float)($row['sale_price'] ?? 0), 2),
            ], '`id` = :id AND `product_id` = :pid', ['id' => (int)$priceId, 'pid' => (int)$id]);
        }

        // 若要求重新应用加价
        if (!empty($_POST['reapply_markup'])) {
            UpstreamService::reapplyMarkup((int)$id);
        }
        $this->adminLog("编辑产品 #{$id} {$data['name']}");
        flash('success', '保存成功');
        redirect('/admin/products/' . (int)$id . '/edit');
    }

    /** 上架/下架 */
    public function toggle(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $product = DB::get("SELECT * FROM `products` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if ($product) {
            $new = (int)$product['status'] ? 0 : 1;
            DB::update('products', ['status' => $new], '`id` = :id', ['id' => (int)$id]);
            $this->adminLog(($new ? '上架' : '下架') . "产品 #{$id}");
        }
        redirect('/admin/products');
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $hasHosts = DB::count("SELECT COUNT(*) FROM `hosts` WHERE `product_id` = ?", [(int)$id]);
        if ($hasHosts > 0) {
            flash('error', '该产品下还有实例，无法删除');
            redirect('/admin/products');
        }
        DB::beginTransaction();
        try {
            $optIds = array_column(DB::all("SELECT `id` FROM `product_config_options` WHERE `product_id` = ?", [(int)$id]), 'id');
            if ($optIds) {
                $in = implode(',', array_map('intval', $optIds));
                DB::query("DELETE FROM `product_config_subs` WHERE `option_id` IN ({$in})");
            }
            DB::delete('product_config_options', '`product_id` = ?', [(int)$id]);
            DB::delete('product_prices', '`product_id` = ?', [(int)$id]);
            DB::delete('products', '`id` = ?', [(int)$id]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            flash('error', '删除失败');
            redirect('/admin/products');
        }
        $this->adminLog("删除产品 #{$id}");
        flash('success', '已删除');
        redirect('/admin/products');
    }
}
