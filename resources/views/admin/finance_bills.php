<div class="card" style="margin-bottom:16px">
  <form method="get" action="/admin/finance/bills" style="display:flex;gap:10px;flex-wrap:wrap">
    <select class="form-control" name="type" style="max-width:140px">
      <option value="">全部类型</option>
      <?php foreach (['order' => '订单', 'renew' => '续费', 'recharge' => '充值'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filter['type'] === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-control" name="status" style="max-width:140px">
      <option value="">全部状态</option>
      <option value="paid" <?= $filter['status'] === 'paid' ? 'selected' : '' ?>>已支付</option>
      <option value="unpaid" <?= $filter['status'] === 'unpaid' ? 'selected' : '' ?>>未支付</option>
    </select>
    <input class="form-control" name="kw" style="max-width:220px" placeholder="账单号/用户名" value="<?= e($filter['kw']) ?>">
    <button class="btn btn-sm" type="submit">筛选</button>
  </form>
  <div style="margin-top:8px;font-size:14px">筛选条件下已收金额：<strong><?= e(money($income)) ?></strong></div>
</div>
<table class="table">
  <thead><tr><th>ID</th><th>账单号</th><th>用户</th><th>类型</th><th>标题</th><th>金额</th><th>支付方式</th><th>状态</th><th>支付时间</th></tr></thead>
  <tbody>
    <?php foreach ($bills as $b): ?>
      <tr>
        <td><?= (int)$b['id'] ?></td>
        <td><?= e($b['bill_no']) ?></td>
        <td><?= e($b['username']) ?></td>
        <td><?= ['order' => '订单', 'renew' => '续费', 'recharge' => '充值'][$b['type']] ?? e($b['type']) ?></td>
        <td><?= e($b['title']) ?></td>
        <td><?= e(money((float)$b['amount'])) ?></td>
        <td><?= e($b['payment'] ?? '') ?></td>
        <td><?= $b['status'] === 'paid' ? '<span class="badge badge-paid">已支付</span>' : '<span class="badge badge-unpaid">未支付</span>' ?></td>
        <td><?= e($b['paid_at'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $pagination ?>
