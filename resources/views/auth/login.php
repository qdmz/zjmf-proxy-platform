<div class="auth-wrap">
  <div class="auth-box">
    <div class="auth-card">
      <div class="auth-logo">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><line x1="6" y1="6.5" x2="6.01" y2="6.5"/><line x1="6" y1="17.5" x2="6.01" y2="17.5"/></svg>
      </div>
      <h2>欢迎回来</h2>
      <p class="auth-sub">登录您的账户，管理云服务器</p>
      <form method="post" action="/login">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next ?? '/') ?>">
        <div class="form-group">
          <label>用户名</label>
          <input class="form-control" name="username" required autocomplete="username" placeholder="请输入用户名">
        </div>
        <div class="form-group">
          <label>密码</label>
          <input class="form-control" type="password" name="password" required autocomplete="current-password" placeholder="请输入密码">
        </div>
        <?php if ($captcha ?? false): ?>
        <div class="form-group">
          <label>验证码</label>
          <div style="display:flex;gap:10px;align-items:center">
            <input class="form-control" name="captcha" required maxlength="8" style="flex:1" autocomplete="off" placeholder="输入右侧字符">
            <img src="/captcha" alt="验证码" title="点击刷新" style="height:44px;cursor:pointer;border:1.5px solid var(--border);border-radius:10px" onclick="this.src='/captcha?'+Date.now()">
          </div>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary btn-block btn-lg" type="submit" style="margin-top:6px">登 录</button>
        <p style="margin-top:20px;text-align:center;font-size:14px;color:var(--muted)"><a href="/forgot">忘记密码？</a> · 还没有账号？<a href="/register" style="font-weight:700">立即注册</a></p>
      </form>
    </div>
  </div>
</div>
