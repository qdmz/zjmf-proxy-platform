<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;

class TicketController extends Controller
{
    public function index(): string
    {
        $user = $this->requireLogin();
        $tickets = DB::all(
            "SELECT * FROM `tickets` WHERE `user_id` = ? ORDER BY `updated_at` DESC LIMIT 50",
            [(int)$user['id']]
        );
        return $this->view('ticket/index', ['title' => '我的工单', 'tickets' => $tickets, 'user' => $user]);
    }

    public function create(): string
    {
        $user = $this->requireLogin();
        $hosts = DB::all("SELECT `id`,`domain` FROM `hosts` WHERE `user_id` = ?", [(int)$user['id']]);
        return $this->view('ticket/create', ['title' => '提交工单', 'hosts' => $hosts, 'user' => $user]);
    }

    public function store(): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $hostId = (int)($_POST['host_id'] ?? 0);
        if ($title === '' || $content === '') {
            flash('error', '标题和内容不能为空');
            redirect('/tickets/create');
        }
        $tid = DB::insert('tickets', [
            'user_id' => (int)$user['id'],
            'host_id' => $hostId,
            'title' => mb_substr($title, 0, 200),
        ]);
        DB::insert('ticket_replies', [
            'ticket_id' => $tid,
            'user_id' => (int)$user['id'],
            'is_admin' => 0,
            'content' => $content,
        ]);
        redirect('/tickets/' . $tid);
    }

    public function detail(string $id): string
    {
        $user = $this->requireLogin();
        $ticket = DB::get(
            "SELECT * FROM `tickets` WHERE `id` = ? AND `user_id` = ? LIMIT 1",
            [(int)$id, (int)$user['id']]
        );
        if (!$ticket) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '工单不存在']);
        }
        $replies = DB::all("SELECT * FROM `ticket_replies` WHERE `ticket_id` = ? ORDER BY `id`", [(int)$id]);
        return $this->view('ticket/detail', [
            'title' => '工单详情', 'ticket' => $ticket, 'replies' => $replies, 'user' => $user,
        ]);
    }

    public function reply(string $id): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $ticket = DB::get(
            "SELECT * FROM `tickets` WHERE `id` = ? AND `user_id` = ? LIMIT 1",
            [(int)$id, (int)$user['id']]
        );
        if (!$ticket || $ticket['status'] === 'closed') {
            redirect('/tickets/' . (int)$id);
        }
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            redirect('/tickets/' . (int)$id);
        }
        DB::insert('ticket_replies', [
            'ticket_id' => (int)$id,
            'user_id' => (int)$user['id'],
            'is_admin' => 0,
            'content' => $content,
        ]);
        DB::update('tickets', ['status' => 'open', 'updated_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => (int)$id]);
        redirect('/tickets/' . (int)$id);
    }
}
