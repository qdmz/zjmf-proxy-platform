<div class="container">
  <div class="auth-box">
    <div class="card">
      <h2>用户登录</h2>
      <form method="post" action="/login">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next ?? '/') ?>">
        <div class="form-group">
          <label>用户名</label>
          <input class="form-control" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
          <label>密码</label>
          <input class="form-control" type="password" name="password" required autocomplete="current-password">
        </div>
        <?php if ($captcha ?? false): ?>
        <div class="form-group">
          <label>验证码</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input class="form-control" name="captcha" required maxlength="8" style="flex:1" autocomplete="off" placeholder="输入右侧字符">
            <img src="/captcha" alt="验证码" title="点击刷新" style="height:38px;cursor:pointer;border:1px solid #ddd;border-radius:4px" onclick="this.src='/captcha?'+Date.now()">
          </div>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary" style="width:100%" type="submit">登录</button>
        <p style="margin-top:16px;text-align:center;font-size:14px"><a href="/forgot">忘记密码？</a> · 还没有账号？<a href="/register">立即注册</a></p>
      </form>
    </div>
  </div>
</div>
