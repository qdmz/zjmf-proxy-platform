<div class="container">
  <div class="auth-box">
    <div class="card">
      <h2>管理后台登录</h2>
      <form method="post" action="/admin/login">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>管理员账号</label>
          <input class="form-control" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
          <label>密码</label>
          <input class="form-control" type="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary" style="width:100%" type="submit">登录</button>
      </form>
    </div>
  </div>
</div>
