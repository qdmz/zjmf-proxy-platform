<div class="container section">
  <h2>修改密码</h2>
  <div class="card" style="max-width:560px">
    <form method="post" action="/console/password">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>当前密码</label>
        <input class="form-control" type="password" name="old_password" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label>新密码</label>
        <input class="form-control" type="password" name="new_password" required minlength="6" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label>确认新密码</label>
        <input class="form-control" type="password" name="confirm_password" required autocomplete="new-password">
      </div>
      <div class="form-group">
        <button class="btn" type="submit">确认修改</button>
        <a class="btn btn-sm" href="/console/profile">返回个人资料</a>
      </div>
    </form>
    <p style="color:#888;font-size:13px">修改成功后将退出登录，请用新密码重新登录。</p>
  </div>
</div>
