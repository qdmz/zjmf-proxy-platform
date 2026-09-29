<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;

class DashboardController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $today = date('Y-m-d');
        $stats = [
            'users' => DB::count("SELECT COUNT(*) FROM `users` WHERE `role` = 'user'"),
            'products' => DB::count("SELECT COUNT(*) FROM `products` WHERE `status` = 1"),
            'hosts' => DB::count("SELECT COUNT(*) FROM `hosts` WHERE `status` = 'active'"),
            'today_income' => (float) (DB::get(
                "SELECT COALESCE(SUM(amount),0) AS s FROM `bills` WHERE `status` = 'paid' AND `type` != 'recharge' AND DATE(`paid_at`) = ?",
                [$today]
            )['s'] ?? 0),
            'month_income' => (float) (DB::get(
                "SELECT COALESCE(SUM(amount),0) AS s FROM `bills` WHERE `status` = 'paid' AND `type` != 'recharge' AND DATE_FORMAT(`paid_at`,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')"
            )['s'] ?? 0),
            'pending_orders' => DB::count("SELECT COUNT(*) FROM `orders` WHERE `status` = 'failed'"),
            'open_tickets' => DB::count("SELECT COUNT(*) FROM `tickets` WHERE `status` = 'open'"),
            'unpaid_bills' => DB::count("SELECT COUNT(*) FROM `bills` WHERE `status` = 'unpaid'"),
        ];
        $recentOrders = DB::all(
            "SELECT o.*, u.username FROM `orders` o LEFT JOIN `users` u ON u.id = o.user_id
             ORDER BY o.`id` DESC LIMIT 8"
        );
        // 近7天收入
        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $sum = (float) (DB::get(
                "SELECT COALESCE(SUM(amount),0) AS s FROM `bills` WHERE `status`='paid' AND `type`!='recharge' AND DATE(`paid_at`) = ?",
                [$d]
            )['s'] ?? 0);
            $chart[] = ['date' => substr($d, 5), 'amount' => $sum];
        }
        return $this->view('admin/dashboard', [
            'title' => '仪表盘',
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'chart' => $chart,
            'user' => $admin,
        ], 'layout_admin');
    }
}
