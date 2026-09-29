<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Router;

$router = new Router();

// ---------------- 前台 ----------------
$router->get('/', 'HomeController@index');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@doLogin');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@doRegister');
$router->get('/logout', 'AuthController@logout');
$router->get('/captcha', 'AuthController@captcha');
$router->get('/activate', 'AuthController@activate');
$router->post('/resend-activate', 'AuthController@resendActivate');
$router->get('/forgot', 'AuthController@showForgot');
$router->post('/forgot', 'AuthController@doForgot');
$router->get('/reset-password', 'AuthController@showReset');
$router->post('/reset-password', 'AuthController@doReset');

$router->get('/shop', 'ShopController@index');
$router->get('/shop/{id}', 'ShopController@detail');
$router->post('/shop/quote', 'ShopController@quote');

$router->post('/order/create', 'OrderController@create');
$router->get('/orders', 'OrderController@index');
$router->get('/orders/{id}', 'OrderController@detail');
$router->post('/orders/{id}/cancel', 'OrderController@cancel');
$router->post('/orders/{id}/delete', 'OrderController@destroy');

// ---------------- 支付 ----------------
$router->get('/pay/{billNo}', 'PayController@cashier');
$router->post('/pay/{billNo}', 'PayController@doPay');
$router->post('/pay/notify/epay', 'PayController@notifyEpay');
$router->get('/pay/return/{billNo}', 'PayController@returnPay');
$router->get('/pay/result/{billNo}', 'PayController@result');
$router->get('/recharge', 'PayController@recharge');
$router->post('/recharge', 'PayController@doRecharge');
$router->get('/transactions', 'PayController@transactions');

// ---------------- 用户控制台 ----------------
$router->get('/console', 'ConsoleController@index');
$router->get('/console/host/{id}', 'ConsoleController@host');
$router->post('/console/host/{id}/action', 'ConsoleController@action');
$router->post('/console/host/{id}/sync', 'ConsoleController@sync');
$router->get('/console/host/{id}/power', 'ConsoleController@powerStatus');
$router->get('/console/host/{id}/os', 'ConsoleController@reinstallOs');
$router->post('/console/host/{id}/renew', 'ConsoleController@renew');
$router->post('/console/host/{id}/cancel', 'ConsoleController@cancel');
$router->get('/messages', 'ConsoleController@messages');
// ---------------- 个人资料 ----------------
$router->get('/console/profile', 'ProfileController@index');
$router->post('/console/profile', 'ProfileController@update');
$router->get('/console/password', 'ProfileController@password');
$router->post('/console/password', 'ProfileController@updatePassword');
$router->get('/verify-email', 'ProfileController@verifyEmail');

// ---------------- 工单 ----------------
$router->get('/tickets', 'TicketController@index');
$router->get('/tickets/create', 'TicketController@create');
$router->post('/tickets/create', 'TicketController@store');
$router->get('/tickets/{id}', 'TicketController@detail');
$router->post('/tickets/{id}/reply', 'TicketController@reply');

// ---------------- 公告 / FAQ / 客服 ----------------
$router->get('/announcements', 'AnnouncementController@index');
$router->get('/announcements/{id}', 'AnnouncementController@detail');
$router->get('/faq', 'FaqController@index');
$router->post('/chat/send', 'ChatController@send');
$router->post('/chat/transfer', 'ChatController@transfer');

// ---------------- 后台 ----------------
$router->get('/admin/login', 'Admin\LoginController@show');
$router->post('/admin/login', 'Admin\LoginController@doLogin');
$router->get('/admin/logout', 'Admin\LoginController@logout');

$router->get('/admin', 'Admin\DashboardController@index');

$router->get('/admin/upstream', 'Admin\UpstreamController@index');
$router->get('/admin/upstream/create', 'Admin\UpstreamController@create');
$router->post('/admin/upstream/save', 'Admin\UpstreamController@store');
$router->get('/admin/upstream/{id}/edit', 'Admin\UpstreamController@edit');
$router->post('/admin/upstream/{id}/delete', 'Admin\UpstreamController@delete');
$router->post('/admin/upstream/{id}/test', 'Admin\UpstreamController@test');
$router->post('/admin/upstream/{id}/sync', 'Admin\UpstreamController@sync');
$router->get('/admin/upstream/logs', 'Admin\UpstreamController@logs');

$router->get('/admin/products', 'Admin\ProductController@index');
$router->post('/admin/products/batch', 'Admin\ProductController@batch');
$router->get('/admin/products/{id}/edit', 'Admin\ProductController@edit');
$router->post('/admin/products/{id}/update', 'Admin\ProductController@update');
$router->post('/admin/products/{id}/toggle', 'Admin\ProductController@toggle');
$router->post('/admin/products/{id}/delete', 'Admin\ProductController@delete');

$router->get('/admin/orders', 'Admin\OrderAdminController@index');
$router->get('/admin/orders/{id}', 'Admin\OrderAdminController@detail');
$router->post('/admin/orders/{id}/retry', 'Admin\OrderAdminController@retry');
$router->post('/admin/orders/{id}/markfailed', 'Admin\OrderAdminController@markFailed');
$router->post('/admin/orders/{id}/refund', 'Admin\OrderAdminController@refund');

$router->get('/admin/hosts', 'Admin\HostAdminController@index');
$router->get('/admin/hosts/{id}', 'Admin\HostAdminController@detail');
$router->post('/admin/hosts/{id}/sync', 'Admin\HostAdminController@sync');
$router->post('/admin/hosts/provider/{providerId}/sync', 'Admin\HostAdminController@syncProvider');

$router->get('/admin/users', 'Admin\UserController@index');
$router->get('/admin/users/admin/create', 'Admin\UserController@create');
$router->post('/admin/users/admin/store', 'Admin\UserController@storeAdmin');
$router->get('/admin/users/{id}', 'Admin\UserController@detail');
$router->post('/admin/users/{id}/toggle', 'Admin\UserController@toggle');
$router->post('/admin/users/{id}/adjust', 'Admin\UserController@adjust');

$router->get('/admin/finance/bills', 'Admin\FinanceController@bills');
$router->get('/admin/logs', 'Admin\FinanceController@adminLogs');

$router->get('/admin/tickets', 'Admin\TicketAdminController@index');
$router->get('/admin/tickets/{id}', 'Admin\TicketAdminController@detail');
$router->post('/admin/tickets/{id}/reply', 'Admin\TicketAdminController@reply');

$router->get('/admin/settings', 'Admin\SettingController@index');
$router->post('/admin/settings/save', 'Admin\SettingController@save');
$router->post('/admin/settings/test-mail', 'Admin\SettingController@testMail');

$router->get('/admin/announcements', 'Admin\AnnouncementController@index');
$router->get('/admin/announcements/create', 'Admin\AnnouncementController@create');
$router->post('/admin/announcements/store', 'Admin\AnnouncementController@store');
$router->get('/admin/announcements/{id}/edit', 'Admin\AnnouncementController@edit');
$router->post('/admin/announcements/{id}/update', 'Admin\AnnouncementController@update');
$router->post('/admin/announcements/{id}/delete', 'Admin\AnnouncementController@delete');

$router->get('/admin/faqs', 'Admin\FaqController@index');
$router->get('/admin/faqs/create', 'Admin\FaqController@create');
$router->post('/admin/faqs/store', 'Admin\FaqController@store');
$router->get('/admin/faqs/{id}/edit', 'Admin\FaqController@edit');
$router->post('/admin/faqs/{id}/update', 'Admin\FaqController@update');
$router->post('/admin/faqs/{id}/delete', 'Admin\FaqController@delete');

$router->get('/admin/coupons', 'Admin\CouponController@index');
$router->get('/admin/coupons/create', 'Admin\CouponController@create');
$router->post('/admin/coupons/store', 'Admin\CouponController@store');
$router->get('/admin/coupons/{id}/edit', 'Admin\CouponController@edit');
$router->post('/admin/coupons/{id}/update', 'Admin\CouponController@update');
$router->post('/admin/coupons/{id}/delete', 'Admin\CouponController@delete');
$router->get('/admin/coupons/{id}/usages', 'Admin\CouponController@usages');

// ---------------- 404 ----------------
$router->notFound(function () {
    http_response_code(404);
    return \App\Core\View::render('errors/404', ['title' => '页面不存在'], 'layout');
});

echo $router->run();
