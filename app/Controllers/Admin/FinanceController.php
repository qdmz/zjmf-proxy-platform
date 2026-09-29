<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;

/**
 * 财务：账单、充值记录、管理员日志
 */
class FinanceController extends Controller
{
    public function bills(): string
    {
        $admin = $this->requireAdmin();
        $status = $_GET['status'] ?? '';
        $type = $_GET['type'] ?? '';
        $kw = trim($_GET['kw'] ?? '');
        $where = '1=1';
        $params = [];
        if ($status !== '') { $where .= ' AND b.status = ?'; $params[] = $status; }
        if ($type !== '') { $where .= ' AND b.type = ?'; $params[] = $type; }
        if ($kw !== '') { $where .= ' AND (b.bill_no LIKE ? OR u.username LIKE ?)'; $params[] = "%{$kw}%"; $params[] = "%{$kw}%"; }

        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `bills` b LEFT JOIN `users` u ON u.id = b.user_id WHERE {$where}", $params);
        $bills = DB::all(
            "SELECT b.*, u.username FROM `bills` b LEFT JOIN `users` u ON u.id = b.user_id
             WHERE {$where} ORDER BY b.`id` DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per, ($page - 1) * $per])
        );
        $income = (float)(DB::get("SELECT COALESCE(SUM(amount),0) AS s FROM `bills` b LEFT JOIN `users` u ON u.id = b.user_id WHERE {$where} AND b.status='paid'", $params)['s'] ?? 0);
        return $this->view('admin/finance_bills', [
            'title' => '账单记录', 'bills' => $bills, 'income' => $income,
            'filter' => ['status' => $status, 'type' => $type, 'kw' => $kw],
            'pagination' => paginate($total, $page, $per, '/admin/finance/bills?' . http_build_query(array_filter(['status' => $status, 'type' => $type, 'kw' => $kw]))),
            'user' => $admin,
        ], 'layout_admin');
    }

    public function adminLogs(): string
    {
        $admin = $this->requireAdmin();
        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `admin_logs`");
        $logs = DB::all(
            "SELECT l.*, u.username FROM `admin_logs` l LEFT JOIN `users` u ON u.id = l.admin_id
             ORDER BY l.`id` DESC LIMIT ? OFFSET ?",
            [$per, ($page - 1) * $per]
        );
        return $this->view('admin/admin_logs', [
            'title' => '管理员日志', 'logs' => $logs,
            'pagination' => paginate($total, $page, $per, '/admin/logs'),
            'user' => $admin,
        ], 'layout_admin');
    }
}
