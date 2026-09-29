<div class="container">
  <div class="auth-box" style="max-width:520px">
    <div class="card" style="text-align:center">
      <?php if ($bill && $bill['status'] === 'paid'): ?>
        <div style="font-size:48px">✅</div>
        <h2 style="color:var(--success)">支付成功</h2>
        <p>账单 <?= e($bill['bill_no']) ?> 已支付 <?= e(money((float)$bill['amount'])) ?></p>
        <?php if (!empty($bill['host_id'])): ?>
          <a href="/console/host/<?= (int)$bill['host_id'] ?>" class="btn btn-primary" style="margin-top:16px">管理我的服务器</a>
        <?php else: ?>
          <a href="/console" class="btn btn-primary" style="margin-top:16px">前往控制台</a>
        <?php endif; ?>
      <?php else: ?>
        <div style="font-size:48px">⏳</div>
        <h2>等待支付</h2>
        <p>账单尚未支付，请返回收银台完成支付。</p>
        <?php if ($bill): ?><a href="/pay/<?= e($bill['bill_no']) ?>" class="btn btn-primary" style="margin-top:16px">去支付</a><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
