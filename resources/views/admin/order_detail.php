<div class="detail-grid">
  <div class="card">
    <h3>订单信息</h3>
    <div class="kv"><span>订单号</span><span><?= e($order['order_no']) ?></span></div>
    <div class="kv"><span>用户</span><span><?= e($order['username']) ?></span></div>
    <div class="kv"><span>产品</span><span><?= e($order['product_name'] ?? '') ?></span></div>
    <div class="kv"><span>周期</span><span><?= e(cycle_name($order['billingcycle'])) ?> × <?= (int)$order['qty'] ?></span></div>
    <div class="kv"><span>金额</span><span><?= e(money((float)$order['amount'])) ?></span></div>
    <div class="kv"><span>状态</span><span><span class="badge badge-<?= e($order['status']) ?>"><?= e(order_status_name($order['status'])) ?></span></span></div>
    <div class="kv"><span>主机名</span><span><?= e($order['host_name']) ?></span></div>
    <?php if (!empty($snapshot['configoption_name'])): ?>
      <div class="kv"><span>配置</span><span><?= e(implode('、', (array)$snapshot['configoption_name'])) ?></span></div>
    <?php endif; ?>
    <?php if ($order['fail_reason']): ?>
      <div class="alert alert-error" style="margin-top:12px"><?= e($order['fail_reason']) ?></div>
    <?php endif; ?>
    <div style="margin-top:16px;display:flex;gap:10px">
      <?php if ($order['status'] === 'failed'): ?>
        <form method="post" action="/admin/orders/<?= (int)$order['id'] ?>/retry">
          <?= csrf_field() ?>
          <button class="btn btn-primary btn-sm" type="submit">重试开通</button>
        </form>
        <form method="post" action="/admin/orders/<?= (int)$order['id'] ?>/markfailed">
          <?= csrf_field() ?>
          <button class="btn btn-sm" type="submit">标记人工处理</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <h3>关联信息</h3>
    <?php if ($bill): ?>
      <div class="kv"><span>账单号</span><span><?= e($bill['bill_no']) ?></span></div>
      <div class="kv"><span>账单状态</span><span><?= $bill['status'] === 'paid' ? '已支付' : '未支付' ?></span></div>
      <div class="kv"><span>支付方式</span><span><?= e($bill['payment'] ?? '') ?></span></div>
      <div class="kv"><span>支付时间</span><span><?= e($bill['paid_at'] ?? '') ?></span></div>
      <div class="kv"><span>上游账单</span><span><?= e($bill['upstream_invoice_id'] ?? '') ?></span></div>
    <?php endif; ?>
    <?php if ($host): ?>
      <div class="kv"><span>实例</span><span><a href="/admin/hosts/<?= (int)$host['id'] ?>"><?= e($host['domain']) ?></a></span></div>
      <div class="kv"><span>上游 HostID</span><span><?= (int)$host['upstream_host_id'] ?></span></div>
    <?php endif; ?>
  </div>
</div>
