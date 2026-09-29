<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;

/** 公告（前台） */
class AnnouncementController extends Controller
{
    public function index(): string
    {
        $page = $this->page();
        $per = 10;
        $where = "`status` = 1 AND (`published_at` IS NULL OR `published_at` <= NOW())";
        $total = DB::count("SELECT COUNT(*) FROM `announcements` WHERE {$where}");
        $list = DB::all(
            "SELECT * FROM `announcements` WHERE {$where}
             ORDER BY `is_pinned` DESC, `published_at` DESC, `id` DESC LIMIT ? OFFSET ?",
            [$per, ($page - 1) * $per]
        );
        return $this->view('announcement/index', [
            'title' => '公告', 'list' => $list,
            'pagination' => paginate($total, $page, $per, '/announcements'),
            'user' => \App\Core\Auth::user(),
        ]);
    }

    public function detail(string $id): string
    {
        $a = DB::get(
            "SELECT * FROM `announcements` WHERE `id` = ? AND `status` = 1
             AND (`published_at` IS NULL OR `published_at` <= NOW()) LIMIT 1",
            [(int)$id]
        );
        if (!$a) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '公告不存在']);
        }
        return $this->view('announcement/detail', [
            'title' => $a['title'], 'a' => $a, 'user' => \App\Core\Auth::user(),
        ]);
    }
}
