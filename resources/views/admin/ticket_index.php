<div class="card" style="margin-bottom:16px">
  <form method="get" action="/admin/tickets" style="display:flex;gap:10px">
    <select class="form-control" name="status" style="max-width:160px">
      <option value="">全部状态</option>
      <?php foreach (['open' => '待回复', 'replied' => '已回复', 'closed' => '已关闭'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filter['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm" type="submit">筛选</button>
  </form>
</div>
<table class="table">
  <thead><tr><th>ID</th><th>标题</th><th>用户</th><th>状态</th><th>更新时间</th><th>操作</th></tr></thead>
  <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr>
        <td><?= (int)$t['id'] ?></td>
        <td><?= e($t['title']) ?></td>
        <td><?= e($t['username']) ?></td>
        <td><span class="badge badge-<?= e($t['status']) ?>"><?= e(ticket_status_name($t['status'])) ?></span></td>
        <td><?= e($t['updated_at']) ?></td>
        <td><a href="/admin/tickets/<?= (int)$t['id'] ?>" class="btn btn-sm">处理</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $pagination ?>
