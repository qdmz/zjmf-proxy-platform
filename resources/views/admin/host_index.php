<div class="card" style="margin-bottom:16px">
  <form method="get" action="/admin/hosts" style="display:flex;gap:10px">
    <select class="form-control" name="status" style="max-width:160px">
      <option value="">全部状态</option>
      <?php foreach (['pending' => '开通中', 'active' => '运行中', 'suspended' => '已暂停', 'cancelled' => '已取消'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filter['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <input class="form-control" name="kw" style="max-width:220px" placeholder="主机名/用户名" value="<?= e($filter['kw']) ?>">
    <button class="btn btn-sm" type="submit">筛选</button>
  </form>
</div>
<table class="table">
  <thead><tr><th>ID</th><th>主机名</th><th>用户</th><th>产品</th><th>IP</th><th>到期</th><th>状态</th><th>操作</th></tr></thead>
  <tbody>
    <?php foreach ($hosts as $h): ?>
      <tr>
        <td><?= (int)$h['id'] ?></td>
        <td><?= e($h['domain']) ?></td>
        <td><?= e($h['username']) ?></td>
        <td><?= e($h['product_name'] ?? '') ?></td>
        <td><?= e($h['dedicated_ip']) ?></td>
        <td><?= e($h['nextduedate'] ?? '') ?></td>
        <td><span class="badge badge-<?= e($h['status']) ?>"><?= e(host_status_name($h['status'])) ?></span></td>
        <td><a href="/admin/hosts/<?= (int)$h['id'] ?>" class="btn btn-sm">详情</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $pagination ?>
