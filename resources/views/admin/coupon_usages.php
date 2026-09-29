<div class="page-head"><h2><?= e($title) ?></h2><a href="/admin/coupons">返回列表</a></div>
<div class="card">
  <table class="table">
    <thead><tr><th>ID</th><th>用户</th><th>订单号</th><th>优惠金额</th><th>使用时间</th></tr></thead>
    <tbody>
      <?php foreach ($list as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= e($u['username'] ?? ('#' . $u['user_id'])) ?></td>
        <td><?= e($u['order_no'] ?? ('#' . $u['order_id'])) ?></td>
        <td><?= e($u['discount_amount']) ?> 元</td>
        <td><?= e($u['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="5" class="muted">暂无使用记录</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
