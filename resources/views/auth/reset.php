<div class="container">
  <div class="auth-box">
    <div class="card">
      <h2>重置密码</h2>
      <form method="post" action="/reset-password">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
        <div class="form-group">
          <label>新密码（至少 6 位）</label>
          <input class="form-control" type="password" name="password" required minlength="6" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label>确认新密码</label>
          <input class="form-control" type="password" name="password_confirm" required minlength="6" autocomplete="new-password">
        </div>
        <button class="btn btn-primary" style="width:100%" type="submit">重置密码</button>
      </form>
    </div>
  </div>
</div>
