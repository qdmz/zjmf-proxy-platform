<div class="card" style="margin-bottom:16px">
  <form method="get" action="/admin/users" style="display:flex;gap:10px">
    <input class="form-control" name="kw" style="max-width:220px" placeholder="用户名/邮箱" value="<?= e($filter['kw']) ?>">
    <select class="form-control" name="role" style="max-width:140px">
      <option value="user"<?= $filter['role'] === 'user' ? ' selected' : '' ?>>普通用户</option>
      <option value="admin"<?= $filter['role'] === 'admin' ? ' selected' : '' ?>>管理员</option>
    </select>
    <button class="btn btn-sm" type="submit">搜索</button>
    <a href="/admin/users/admin/create" class="btn btn-sm">添加管理员</a>
  </form>
</div>
<table class="table">
  <thead><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>余额</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead>
  <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= e($u['username']) ?><?= $u['role'] === 'admin' ? ' <span class="badge badge-pending">管理员</span>' : '' ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e(money((float)$u['balance'])) ?></td>
        <td><?= $u['status'] ? '<span class="badge badge-active">正常</span>' : '<span class="badge badge-failed">禁用</span>' ?></td>
        <td><?= e($u['created_at']) ?></td>
        <td><a href="/admin/users/<?= (int)$u['id'] ?>" class="btn btn-sm">详情</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $pagination ?>
