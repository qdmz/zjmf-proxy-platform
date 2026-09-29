<div class="card" style="margin-bottom:16px">
  <form method="get" action="/admin/orders" style="display:flex;gap:10px">
    <select class="form-control" name="status" style="max-width:160px">
      <option value="">全部状态</option>
      <?php foreach (['pending' => '待支付', 'active' => '已开通', 'failed' => '开通失败', 'cancelled' => '已取消'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filter['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <input class="form-control" name="kw" style="max-width:220px" placeholder="订单号/用户名" value="<?= e($filter['kw']) ?>">
    <button class="btn btn-sm" type="submit">筛选</button>
  </form>
</div>
<table class="table">
  <thead><tr><th>ID</th><th>订单号</th><th>用户</th><th>产品</th><th>金额</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
  <tbody>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><?= (int)$o['id'] ?></td>
        <td><?= e($o['order_no']) ?></td>
        <td><?= e($o['username']) ?></td>
        <td><?= e($o['product_name'] ?? '') ?></td>
        <td><?= e(money((float)$o['amount'])) ?></td>
        <td><span class="badge badge-<?= e($o['status']) ?>"><?= e(order_status_name($o['status'])) ?></span></td>
        <td><?= e($o['created_at']) ?></td>
        <td><a href="/admin/orders/<?= (int)$o['id'] ?>" class="btn btn-sm">详情</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $pagination ?>
