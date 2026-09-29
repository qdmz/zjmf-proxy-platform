<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;

/** 公告管理 */
class AnnouncementController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `announcements`");
        $list = DB::all(
            "SELECT * FROM `announcements` ORDER BY `id` DESC LIMIT ? OFFSET ?",
            [$per, ($page - 1) * $per]
        );
        return $this->view('admin/announcement_index', [
            'title' => '公告管理', 'list' => $list,
            'pagination' => paginate($total, $page, $per, '/admin/announcements'),
            'user' => $admin,
        ], 'layout_admin');
    }

    public function create(): string
    {
        $admin = $this->requireAdmin();
        return $this->view('admin/announcement_form', [
            'title' => '发布公告', 'a' => null, 'user' => $admin,
        ], 'layout_admin');
    }

    public function store(): void
    {
        $admin = $this->requireAdmin();
        csrf_check();
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title === '' || $content === '') {
            flash('error', '标题和内容不能为空');
            redirect('/admin/announcements/create');
        }
        DB::insert('announcements', [
            'title' => $title,
            'content' => $content,
            'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
            'status' => isset($_POST['status']) ? 1 : 0,
            'published_at' => date('Y-m-d H:i:s'),
        ]);
        $this->adminLog("发布公告：{$title}");
        flash('success', '公告已发布');
        redirect('/admin/announcements');
    }

    public function edit(string $id): string
    {
        $admin = $this->requireAdmin();
        $a = DB::get("SELECT * FROM `announcements` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$a) {
            flash('error', '公告不存在');
            redirect('/admin/announcements');
        }
        return $this->view('admin/announcement_form', [
            'title' => '编辑公告', 'a' => $a, 'user' => $admin,
        ], 'layout_admin');
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title === '' || $content === '') {
            flash('error', '标题和内容不能为空');
            redirect('/admin/announcements/' . (int)$id . '/edit');
        }
        DB::update('announcements', [
            'title' => $title,
            'content' => $content,
            'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
            'status' => isset($_POST['status']) ? 1 : 0,
        ], '`id` = :id', ['id' => (int)$id]);
        $this->adminLog("编辑公告 #{$id}：{$title}");
        flash('success', '已保存');
        redirect('/admin/announcements');
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        DB::delete('announcements', '`id` = :id', ['id' => (int)$id]);
        $this->adminLog("删除公告 #{$id}");
        flash('success', '已删除');
        redirect('/admin/announcements');
    }
}
