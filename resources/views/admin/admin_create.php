<div class="card" style="max-width:480px">
  <form method="post" action="/admin/users/admin/store">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>管理员用户名</label>
      <input class="form-control" name="username" required pattern="[a-zA-Z0-9_]{4,20}">
    </div>
    <div class="form-group">
      <label>密码（至少 6 位）</label>
      <input class="form-control" type="password" name="password" required minlength="6">
    </div>
    <button class="btn btn-primary" type="submit">添加</button>
  </form>
</div>
