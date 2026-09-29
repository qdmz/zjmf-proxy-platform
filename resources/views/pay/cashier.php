<div class="container">
  <div class="auth-box" style="max-width:520px">
    <div class="card">
      <h2>收银台</h2>
      <div class="kv"><span>账单号</span><span><?= e($bill['bill_no']) ?></span></div>
      <div class="kv"><span>账单内容</span><span><?= e($bill['title']) ?></span></div>
      <div class="kv"><span>应付金额</span><span style="font-size:22px;color:var(--danger);font-weight:700"><?= e(money((float)$bill['amount'])) ?></span></div>

      <form method="post" action="/pay/<?= e($bill['bill_no']) ?>" style="margin-top:20px">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>支付方式</label>
          <div class="opt-list">
            <div class="opt-item selected" data-pay="balance">
              余额支付<small>当前余额：<?= e(money((float)$user['balance'])) ?></small>
            </div>
            <?php foreach ($epayTypes as $code => $name): ?>
              <div class="opt-item" data-pay="epay:<?= e($code) ?>"><?= e($name) ?></div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="payment" id="paymentInput" value="balance">
        </div>
        <?php if ((float)$user['balance'] < (float)$bill['amount']): ?>
          <div class="notice">余额不足，请先 <a href="/recharge">充值</a> 或选择在线支付。</div>
        <?php endif; ?>
        <button class="btn btn-primary" style="width:100%" type="submit">确认支付</button>
      </form>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.opt-item[data-pay]').forEach(function (el) {
  el.addEventListener('click', function () {
    document.querySelectorAll('.opt-item[data-pay]').forEach(function (x) { x.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('paymentInput').value = el.dataset.pay;
  });
});
</script>
