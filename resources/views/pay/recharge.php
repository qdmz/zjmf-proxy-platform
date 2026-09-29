<div class="container">
  <div class="auth-box" style="max-width:520px">
    <div class="card">
      <h2>账户充值</h2>
      <p style="color:var(--muted);font-size:14px;margin-bottom:16px">当前余额：<?= e(money((float)$user['balance'])) ?>　<a href="/transactions">资金流水</a>　<a href="/bills">账单明细</a></p>
      <?php if (!$epayEnabled): ?>
        <div class="notice">在线充值暂未开通，请联系管理员。</div>
      <?php else: ?>
        <form method="post" action="/recharge">
          <?= csrf_field() ?>
          <div class="form-group">
            <label>充值金额（元）</label>
            <input class="form-control" name="amount" type="number" min="1" step="0.01" required placeholder="如 100">
          </div>
          <div class="opt-list" style="margin-bottom:16px">
            <?php $i = 0; foreach ([50, 100, 200, 500] as $a): ?>
              <div class="opt-item quick-amt" data-amt="<?= $a ?>"><?= $a ?>元</div>
            <?php endforeach; ?>
          </div>
          <button class="btn btn-primary" style="width:100%" type="submit">去充值</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.quick-amt').forEach(function (el) {
  el.addEventListener('click', function () {
    document.querySelector('input[name="amount"]').value = el.dataset.amt;
  });
});
</script>
