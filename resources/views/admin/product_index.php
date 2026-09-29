<div class="card" style="margin-bottom:16px">
  <form method="get" action="/admin/products" style="display:flex;gap:10px;flex-wrap:wrap">
    <select class="form-control" name="provider_id" style="max-width:200px">
      <option value="0">全部供货商</option>
      <?php foreach ($providers as $pr): ?>
        <option value="<?= (int)$pr['id'] ?>" <?= $filter['provider_id'] == $pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-control" name="status" style="max-width:140px">
      <option value="">全部状态</option>
      <option value="1" <?= $filter['status'] === '1' ? 'selected' : '' ?>>上架</option>
      <option value="0" <?= $filter['status'] === '0' ? 'selected' : '' ?>>下架</option>
    </select>
    <select class="form-control" name="group" style="max-width:160px">
      <option value="">全部分组</option>
      <?php foreach ($groups as $g): ?>
        <option value="<?= e($g) ?>" <?= ($filter['group'] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option>
      <?php endforeach; ?>
    </select>
    <input class="form-control" name="kw" style="max-width:220px" placeholder="产品名称" value="<?= e($filter['kw']) ?>">
    <button class="btn btn-sm" type="submit">筛选</button>
  </form>
</div>
<div class="notice">新同步的产品默认下架，请审核价格后再上架。勾选后可批量上架 / 下架 / 设置分组。</div>
<form method="post" action="/admin/products/batch" id="batchForm">
  <?= csrf_field() ?>
  <table class="table">
    <thead><tr><th><input type="checkbox" id="checkAll"></th><th>ID</th><th>产品名称</th><th>分组</th><th>供货商</th><th>最低价</th><th>库存</th><th>加价策略</th><th>状态</th><th>同步时间</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" class="rowCheck"></td>
          <td><?= (int)$p['id'] ?></td>
          <td><?= e($p['name']) ?></td>
          <td><?= $p['group_name'] !== '' ? e($p['group_name']) : '<span style="color:var(--muted)">—</span>' ?></td>
          <td><?= e($p['provider_name'] ?? '') ?></td>
          <td><?= $p['min_price'] !== null ? e(money((float)$p['min_price'])) : '—' ?></td>
          <td>
            <?php if (empty($p['stock_control'])): ?>
              <span class="badge badge-active">不限</span>
            <?php elseif ((int)$p['stock_qty'] > 0): ?>
              <span class="badge badge-unpaid"><?= (int)$p['stock_qty'] ?> 件</span>
            <?php else: ?>
              <span class="badge badge-failed">缺货</span>
            <?php endif; ?>
          </td>
          <td><?= $p['markup_type'] === 'fixed' ? '固定+' . e(money((float)$p['markup_value'])) : '百分比+' . e((float)$p['markup_value']) . '%' ?></td>
          <td><?= $p['status'] ? '<span class="badge badge-active">上架</span>' : '<span class="badge badge-closed">下架</span>' ?></td>
          <td><?= e($p['synced_at'] ?? '') ?></td>
          <td>
            <a href="/admin/products/<?= (int)$p['id'] ?>/edit" class="btn btn-sm">编辑</a>
            <form method="post" action="/admin/products/<?= (int)$p['id'] ?>/toggle" style="display:inline">
              <?= csrf_field() ?>
              <button class="btn btn-sm" type="submit"><?= $p['status'] ? '下架' : '上架' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="card" style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <span style="color:var(--muted);font-size:14px">批量操作：</span>
    <select class="form-control" name="batch_action" id="batchAction" style="max-width:160px">
      <option value="on">批量上架</option>
      <option value="off">批量下架</option>
      <option value="group">设置分组</option>
    </select>
    <input class="form-control" name="group_name" id="groupNameInput" style="max-width:200px;display:none" placeholder="分组名称（留空=清空）" maxlength="50" list="groupList">
    <datalist id="groupList">
      <?php foreach ($groups as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?>
    </datalist>
    <button class="btn btn-primary btn-sm" type="submit">执行</button>
  </div>
</form>
<?= $pagination ?>
<script>
document.getElementById('checkAll').addEventListener('change', function () {
  var all = document.getElementById('checkAll').checked;
  document.querySelectorAll('.rowCheck').forEach(function (c) { c.checked = all; });
});
document.getElementById('batchAction').addEventListener('change', function () {
  document.getElementById('groupNameInput').style.display = this.value === 'group' ? '' : 'none';
});
document.getElementById('batchForm').addEventListener('submit', function (ev) {
  if (!document.querySelector('.rowCheck:checked')) { alert('请先勾选要操作的产品'); ev.preventDefault(); }
});
</script>
