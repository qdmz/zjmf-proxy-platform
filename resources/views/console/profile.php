<div class="container section">
  <h2>个人资料</h2>
  <div class="card" style="max-width:560px">
    <form method="post" action="/console/profile">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>用户名</label>
        <input class="form-control" value="<?= e($user['username']) ?>" disabled>
      </div>
      <div class="form-group">
        <label>邮箱<?php if (setting('register_email_verify', '0') === '1'): ?> <small style="color:#888">（修改后需点击新邮箱中的确认链接才生效）</small><?php endif; ?></label>
        <input class="form-control" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required placeholder="用于接收通知和找回密码">
      </div>
      <div class="form-group">
        <label>手机号</label>
        <input class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="选填">
      </div>
      <div class="form-group">
        <button class="btn" type="submit">保存修改</button>
        <a class="btn btn-sm" href="/console/password">修改密码</a>
      </div>
    </form>
  </div>
</div>
