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
    <input class="form-control" name="kw" style="max-width:220px" placeholder="产品名称" value="<?= e($filter['kw']) ?>">
    <button class="btn btn-sm" type="submit">筛选</button>
  </form>
</div>
<div class="notice">新同步的产品默认下架，请审核价格后再上架。</div>
<table class="table">
  <thead><tr><th>ID</th><th>产品名称</th><th>供货商</th><th>最低价</th><th>加价策略</th><th>状态</th><th>同步时间</th><th>操作</th></tr></thead>
  <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><?= e($p['name']) ?></td>
        <td><?= e($p['provider_name'] ?? '') ?></td>
        <td><?= $p['min_price'] !== null ? e(money((float)$p['min_price'])) : '—' ?></td>
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
<?= $pagination ?>
