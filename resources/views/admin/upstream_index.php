<div style="margin-bottom:16px"><a href="/admin/upstream/create" class="btn btn-primary btn-sm">添加上游供货商</a></div>
<div class="notice">上游账单由供货商 API 账号的余额支付。开通前请确认上游账号余额充足。</div>
<table class="table">
  <thead><tr><th>ID</th><th>名称</th><th>API 地址</th><th>账号</th><th>产品数</th><th>状态</th><th>操作</th></tr></thead>
  <tbody>
    <?php foreach ($providers as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><?= e($p['name']) ?></td>
        <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($p['base_url']) ?></td>
        <td><?= e($p['account']) ?></td>
        <td><?= (int)$p['product_count'] ?></td>
        <td><?= $p['status'] ? '<span class="badge badge-active">启用</span>' : '<span class="badge badge-closed">禁用</span>' ?></td>
        <td>
          <a href="/admin/upstream/<?= (int)$p['id'] ?>/edit" class="btn btn-sm">编辑</a>
          <button class="btn btn-sm" onclick="testUpstream(<?= (int)$p['id'] ?>)">测试连接</button>
          <button class="btn btn-sm btn-primary" onclick="syncUpstream(<?= (int)$p['id'] ?>)">同步产品</button>
          <form method="post" action="/admin/upstream/<?= (int)$p['id'] ?>/delete" style="display:inline" onsubmit="return confirm('确定删除吗？')">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<script>
function testUpstream(id) {
  post('/admin/upstream/' + id + '/test', {}, function (j) { alert(j.message || (j.code === 0 ? '连接成功' : '连接失败')); });
}
function syncUpstream(id) {
  if (!confirm('将从上游拉取产品并覆盖本地价格（新商品默认下架），继续吗？')) return;
  alert('同步中，请稍候…');
  post('/admin/upstream/' + id + '/sync', {}, function (j) { alert(j.message || (j.code === 0 ? '同步成功' : '同步失败')); location.reload(); });
}
</script>
