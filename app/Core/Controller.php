<?php
namespace App\Core;

class Controller
{
    protected function view(string $tpl, array $data = [], ?string $layout = 'layout'): string
    {
        return View::render($tpl, $data, $layout);
    }

    protected function json($data = null, string $msg = 'ok'): void
    {
        json_ok($data, $msg);
    }

    protected function fail(string $msg, int $code = 1): void
    {
        json_fail($msg, $code);
    }

    protected function redirect(string $url): void
    {
        redirect($url);
    }

    /** 需要登录，返回当前用户 */
    protected function requireLogin()
    {
        $user = Auth::user();
        if (!$user) {
            if (is_ajax()) {
                json_fail('请先登录', 401);
            }
            redirect('/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
        }
        return $user;
    }

    /** 需要管理员 */
    protected function requireAdmin()
    {
        $user = $this->requireLogin();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            die('无权访问');
        }
        return $user;
    }

    protected function adminLog(string $action): void
    {
        try {
            DB::insert('admin_logs', [
                'admin_id' => Auth::id(),
                'action' => $action,
                'ip' => client_ip(),
            ]);
        } catch (\Throwable $e) { /* 忽略 */ }
    }

    protected function page(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    protected function perPage(): int
    {
        return (int) Config::get('pagesize', 15);
    }
}
