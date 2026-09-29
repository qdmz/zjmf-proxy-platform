<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;

/** FAQ 管理 */
class FaqController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $list = DB::all("SELECT * FROM `faqs` ORDER BY `sort` DESC, `id` ASC");
        return $this->view('admin/faq_index', [
            'title' => '常见问题管理', 'list' => $list, 'user' => $admin,
        ], 'layout_admin');
    }

    public function create(): string
    {
        $admin = $this->requireAdmin();
        return $this->view('admin/faq_form', ['title' => '添加问题', 'f' => null, 'user' => $admin], 'layout_admin');
    }

    public function store(): void
    {
        $this->requireAdmin();
        csrf_check();
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        if ($question === '' || $answer === '') {
            flash('error', '问题和答案不能为空');
            redirect('/admin/faqs/create');
        }
        DB::insert('faqs', [
            'category' => trim($_POST['category'] ?? '常见问题') ?: '常见问题',
            'question' => $question,
            'answer' => $answer,
            'sort' => (int)($_POST['sort'] ?? 0),
            'status' => isset($_POST['status']) ? 1 : 0,
        ]);
        $this->adminLog("添加 FAQ：{$question}");
        flash('success', '已添加');
        redirect('/admin/faqs');
    }

    public function edit(string $id): string
    {
        $admin = $this->requireAdmin();
        $f = DB::get("SELECT * FROM `faqs` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$f) {
            flash('error', '不存在');
            redirect('/admin/faqs');
        }
        return $this->view('admin/faq_form', ['title' => '编辑问题', 'f' => $f, 'user' => $admin], 'layout_admin');
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        if ($question === '' || $answer === '') {
            flash('error', '问题和答案不能为空');
            redirect('/admin/faqs/' . (int)$id . '/edit');
        }
        DB::update('faqs', [
            'category' => trim($_POST['category'] ?? '常见问题') ?: '常见问题',
            'question' => $question,
            'answer' => $answer,
            'sort' => (int)($_POST['sort'] ?? 0),
            'status' => isset($_POST['status']) ? 1 : 0,
        ], '`id` = :id', ['id' => (int)$id]);
        $this->adminLog("编辑 FAQ #{$id}");
        flash('success', '已保存');
        redirect('/admin/faqs');
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        DB::delete('faqs', '`id` = :id', ['id' => (int)$id]);
        $this->adminLog("删除 FAQ #{$id}");
        flash('success', '已删除');
        redirect('/admin/faqs');
    }
}
