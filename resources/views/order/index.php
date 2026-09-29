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
            <td>
              <a href="/orders/<?= (int)$o['id'] ?>" class="btn btn-sm">详情</a>
              <?php if ($o['status'] === 'pending'): ?>
                <form method="post" action="/orders/<?= (int)$o['id'] ?>/cancel" style="display:inline" onsubmit="return confirm('确定取消该订单吗？')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm">取消</button>
                </form>
              <?php elseif (in_array($o['status'], ['cancelled', 'failed'], true)): ?>
                <form method="post" action="/orders/<?= (int)$o['id'] ?>/delete" style="display:inline" onsubmit="return confirm('确定删除该订单吗？删除后不可恢复。')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-danger">删除</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?= $pagination ?>
  <?php endif; ?>
</div>
