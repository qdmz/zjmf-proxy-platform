<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Services\PaymentService;

class PayController extends Controller
{
    /** 收银台 */
    public function cashier(string $billNo): string
    {
        $user = $this->requireLogin();
        $bill = DB::get(
            "SELECT * FROM `bills` WHERE `bill_no` = ? AND `user_id` = ? LIMIT 1",
            [$billNo, (int)$user['id']]
        );
        if (!$bill) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '账单不存在']);
        }
        if ($bill['status'] === 'paid') {
            redirect('/pay/result/' . $billNo);
        }
        return $this->view('pay/cashier', [
            'title' => '收银台',
            'bill' => $bill,
            'user' => $user,
            'epayEnabled' => PaymentService::epayEnabled(),
            'epayTypes' => PaymentService::epayTypes(),
        ]);
    }

    /** 发起支付 */
    public function doPay(string $billNo): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $bill = DB::get(
            "SELECT * FROM `bills` WHERE `bill_no` = ? AND `user_id` = ? LIMIT 1",
            [$billNo, (int)$user['id']]
        );
        if (!$bill || $bill['status'] === 'paid') {
            redirect('/pay/result/' . $billNo);
        }
        $payment = $_POST['payment'] ?? 'balance';
        if ($payment === 'balance') {
            set_time_limit(120);
            $ret = PaymentService::payWithBalance((int)$bill['id'], (int)$user['id']);
            // 账单已扣款（无论开通是否成功）都去结果页；未扣款才回收银台提示
            $billNow = DB::get("SELECT `status` FROM `bills` WHERE `id` = ? LIMIT 1", [(int)$bill['id']]);
            if ($billNow && $billNow['status'] === 'paid') {
                if (!$ret['ok']) {
                    flash('error', $ret['msg'] . '（款项已扣除，请联系客服处理）');
                }
                redirect('/pay/result/' . $billNo);
            }
            flash('error', $ret['msg'] ?? '支付失败');
            redirect('/pay/' . $billNo);
        }
        // 易支付
        if (strpos($payment, 'epay:') === 0) {
            $type = substr($payment, 5);
            if (!PaymentService::epayEnabled() || !isset(PaymentService::epayTypes()[$type])) {
                flash('error', '该支付方式不可用');
                redirect('/pay/' . $billNo);
            }
            echo PaymentService::epaySubmitForm($bill, $type);
            exit;
        }
        flash('error', '未知支付方式');
        redirect('/pay/' . $billNo);
    }

    /** 易支付异步通知 */
    public function notifyEpay(): void
    {
        $result = PaymentService::epayNotify($_POST);
        echo $result;
        exit;
    }

    /** 易支付同步返回 */
    public function returnPay(string $billNo): string
    {
        $user = $this->requireLogin();
        $bill = DB::get(
            "SELECT * FROM `bills` WHERE `bill_no` = ? AND `user_id` = ? LIMIT 1",
            [$billNo, (int)$user['id']]
        );
        return $this->view('pay/result', [
            'title' => '支付结果',
            'bill' => $bill,
            'user' => $user,
        ]);
    }

    /** 支付结果页 */
    public function result(string $billNo): string
    {
        $user = $this->requireLogin();
        $bill = DB::get(
            "SELECT b.*, o.host_id FROM `bills` b LEFT JOIN `orders` o ON o.id = b.order_id
             WHERE b.`bill_no` = ? AND b.`user_id` = ? LIMIT 1",
            [$billNo, (int)$user['id']]
        );
        return $this->view('pay/result', [
            'title' => '支付结果',
            'bill' => $bill,
            'user' => $user,
        ]);
    }

    /** 余额充值页 */
    public function recharge(): string
    {
        $user = $this->requireLogin();
        return $this->view('pay/recharge', [
            'title' => '账户充值',
            'user' => $user,
            'epayEnabled' => PaymentService::epayEnabled(),
            'epayTypes' => PaymentService::epayTypes(),
        ]);
    }

    public function doRecharge(): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $ret = PaymentService::createRechargeBill((int)$user['id'], $amount);
        if (!$ret['ok']) {
            flash('error', $ret['msg']);
            redirect('/recharge');
        }
        redirect('/pay/' . $ret['bill_no']);
    }

    /** 余额明细 */
    public function transactions(): string
    {
        $user = $this->requireLogin();
        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `transactions` WHERE `user_id` = ?", [(int)$user['id']]);
        $list = DB::all(
            "SELECT * FROM `transactions` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT ? OFFSET ?",
            [(int)$user['id'], $per, ($page - 1) * $per]
        );
        $user = DB::get("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [(int)$user['id']]);
        return $this->view('pay/transactions', [
            'title' => '余额明细',
            'list' => $list,
            'pagination' => paginate($total, $page, $per, '/transactions'),
            'user' => $user,
        ]);
    }
}
