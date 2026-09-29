<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;

/** 工单管理（管理员） */
class TicketAdminController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $status = $_GET['status'] ?? '';
        $where = '1=1';
        $params = [];
        if ($status !== '') { $where .= ' AND t.status = ?'; $params[] = $status; }

        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `tickets` t WHERE {$where}", $params);
        $tickets = DB::all(
            "SELECT t.*, u.username FROM `tickets` t LEFT JOIN `users` u ON u.id = t.user_id
             WHERE {$where} ORDER BY t.`updated_at` DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per, ($page - 1) * $per])
        );
        return $this->view('admin/ticket_index', [
            'title' => '工单管理', 'tickets' => $tickets,
            'filter' => ['status' => $status],
            'pagination' => paginate($total, $page, $per, '/admin/tickets?' . http_build_query(array_filter(['status' => $status]))),
            'user' => $admin,
        ], 'layout_admin');
    }

    public function detail(string $id): string
    {
        $admin = $this->requireAdmin();
        $ticket = DB::get(
            "SELECT t.*, u.username FROM `tickets` t LEFT JOIN `users` u ON u.id = t.user_id
             WHERE t.`id` = ? LIMIT 1", [(int)$id]
        );
        if (!$ticket) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '工单不存在'], 'layout_admin');
        }
        $replies = DB::all("SELECT * FROM `ticket_replies` WHERE `ticket_id` = ? ORDER BY `id`", [(int)$id]);
        return $this->view('admin/ticket_detail', [
            'title' => '工单详情', 'ticket' => $ticket, 'replies' => $replies, 'user' => $admin,
        ], 'layout_admin');
    }

    public function reply(string $id): void
    {
        $admin = $this->requireAdmin();
        csrf_check();
        $ticket = DB::get("SELECT * FROM `tickets` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$ticket) {
            redirect('/admin/tickets');
        }
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            redirect('/admin/tickets/' . (int)$id);
        }
        DB::insert('ticket_replies', [
            'ticket_id' => (int)$id,
            'user_id' => (int)$admin['id'],
            'is_admin' => 1,
            'content' => $content,
        ]);
        DB::update('tickets', [
            'status' => ($_POST['close'] ?? '') === '1' ? 'closed' : 'replied',
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => (int)$id]);
        // 通知用户
        DB::insert('messages', [
            'user_id' => (int)$ticket['user_id'],
            'title' => '工单回复提醒',
            'content' => "您的工单《{$ticket['title']}》已有新的回复，请前往查看。",
        ]);
        $this->adminLog("回复工单 #{$id}");
        redirect('/admin/tickets/' . (int)$id);
    }
}
