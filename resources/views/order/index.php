<div class="container section">
  <h2>我的订单</h2>
  <?php if (empty($orders)): ?>
    <div class="empty">暂无订单</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>订单号</th><th>产品</th><th>金额</th><th>状态</th><th>下单时间</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= e($o['order_no']) ?></td>
            <td><?= e($o['product_name'] ?? '') ?></td>
            <td><?= e(money((float)$o['amount'])) ?></td>
            <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(order_status_name($o['status'])) ?></span></td>
            <td><?= e($o['created_at']) ?></td>
            <td><a href="/orders/<?= (int)$o['id'] ?>" class="btn btn-sm">详情</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?= $pagination ?>
  <?php endif; ?>
</div>
