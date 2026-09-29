<div class="container section">
  <h2>订单详情</h2>
  <div class="detail-grid">
    <div class="card">
      <h3>订单信息</h3>
      <div class="kv"><span>订单号</span><span><?= e($order['order_no']) ?></span></div>
      <div class="kv"><span>产品</span><span><?= e($order['product_name'] ?? '') ?></span></div>
      <div class="kv"><span>付费周期</span><span><?= e(cycle_name($order['billingcycle'])) ?></span></div>
      <div class="kv"><span>数量</span><span><?= (int)$order['qty'] ?></span></div>
      <div class="kv"><span>金额</span><span><?= e(money((float)$order['amount'])) ?></span></div>
      <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>
      <div class="kv"><span>优惠券抵扣</span><span style="color:var(--success)">-<?= e(money((float)$order['discount_amount'])) ?></span></div>
      <?php endif; ?>
      <div class="kv"><span>状态</span><span><span class="badge badge-<?= e($order['status']) ?>"><?= e(order_status_name($order['status'])) ?></span></span></div>
      <?php if (!empty($snapshot['configoption_name'])): ?>
        <div class="kv"><span>配置</span><span><?= e(implode('、', (array)$snapshot['configoption_name'])) ?></span></div>
      <?php endif; ?>
      <div class="kv"><span>下单时间</span><span><?= e($order['created_at']) ?></span></div>
      <?php if ($order['status'] === 'failed' && $order['fail_reason']): ?>
        <div class="alert alert-error" style="margin-top:12px"><?= e($order['fail_reason']) ?></div>
      <?php endif; ?>
    </div>
    <div class="card">
      <h3>关联账单</h3>
      <?php if ($bill): ?>
        <div class="kv"><span>账单号</span><span><?= e($bill['bill_no']) ?></span></div>
        <div class="kv"><span>金额</span><span><?= e(money((float)$bill['amount'])) ?></span></div>
        <div class="kv"><span>状态</span><span><span class="badge badge-<?= e($bill['status']) ?>"><?= $bill['status'] === 'paid' ? '已支付' : '未支付' ?></span></span></div>
        <?php if ($bill['status'] === 'unpaid'): ?>
          <a href="/pay/<?= e($bill['bill_no']) ?>" class="btn btn-primary" style="width:100%;text-align:center;margin-top:12px">去支付</a>
        <?php endif; ?>
      <?php else: ?>
        <p>无账单</p>
      <?php endif; ?>
    </div>
  </div>
</div>
