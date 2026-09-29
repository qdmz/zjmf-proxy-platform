<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

class HomeController extends Controller
{
    public function index(): string
    {
        $products = DB::all(
            "SELECT p.*, pp.price, pp.billingcycle,
                    (SELECT MIN(price) FROM `product_prices` WHERE `product_id` = p.id) AS min_price
             FROM `products` p
             LEFT JOIN `product_prices` pp ON pp.product_id = p.id AND pp.billingcycle = 'monthly'
             WHERE p.`status` = 1
             ORDER BY p.`sort` DESC, p.`id` DESC
             LIMIT 12"
        );
        $announcements = DB::all(
            "SELECT `id`,`title`,`is_pinned` FROM `announcements`
             WHERE `status` = 1 AND (`published_at` IS NULL OR `published_at` <= NOW())
             ORDER BY `is_pinned` DESC, `published_at` DESC, `id` DESC LIMIT 5"
        );
        return $this->view('home/index', [
            'title' => setting('site_name'),
            'products' => $products,
            'announcements' => $announcements,
            'user' => Auth::user(),
        ]);
    }
}
