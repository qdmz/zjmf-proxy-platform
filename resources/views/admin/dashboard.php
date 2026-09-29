<div class="stat-grid">
  <div class="stat"><div class="num"><?= (int)$stats['users'] ?></div><div class="label">注册用户</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['products'] ?></div><div class="label">上架产品</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['hosts'] ?></div><div class="label">运行中实例</div></div>
  <div class="stat"><div class="num"><?= e(money($stats['today_income'])) ?></div><div class="label">今日收入</div></div>
  <div class="stat"><div class="num"><?= e(money($stats['month_income'])) ?></div><div class="label">本月收入</div></div>
  <div class="stat"><div class="num" style="color:var(--danger)"><?= (int)$stats['pending_orders'] ?></div><div class="label">开通失败订单</div></div>
  <div class="stat"><div class="num" style="color:var(--warning)"><?= (int)$stats['open_tickets'] ?></div><div class="label">待处理工单</div></div>
  <div class="stat"><div class="num"><?= (int)$stats['unpaid_bills'] ?></div><div class="label">未支付账单</div></div>
</div>

<h3 style="margin-bottom:12px">近7天收入</h3>
<div class="bar-chart" style="margin-bottom:24px">
  <?php $max = max(1, max(array_column($chart, 'amount'))); ?>
  <?php foreach ($chart as $c): ?>
    <div class="bar" style="height:<?= round($c['amount'] / $max * 100) ?>%" title="<?= e($c['date']) ?>：<?= e(money($c['amount'])) ?>"><span><?= e($c['date']) ?></span></div>
  <?php endforeach; ?>
</div>

<h3 style="margin-bottom:12px">最新订单</h3>
<table class="table">
  <thead><tr><th>订单号</th><th>用户</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
  <tbody>
    <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td><a href="/admin/orders/<?= (int)$o['id'] ?>"><?= e($o['order_no']) ?></a></td>
        <td><?= e($o['username']) ?></td>
        <td><?= e(money((float)$o['amount'])) ?></td>
        <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(order_status_name($o['status'])) ?></span></td>
        <td><?= e($o['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
