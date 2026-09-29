<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;

/** 常见问题（前台） */
class FaqController extends Controller
{
    public function index(): string
    {
        $faqs = DB::all(
            "SELECT * FROM `faqs` WHERE `status` = 1 ORDER BY `sort` DESC, `id` ASC"
        );
        $grouped = [];
        foreach ($faqs as $f) {
            $grouped[$f['category']][] = $f;
        }
        return $this->view('faq/index', [
            'title' => '常见问题', 'grouped' => $grouped,
            'user' => \App\Core\Auth::user(),
        ]);
    }
}
