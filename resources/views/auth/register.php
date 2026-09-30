<div class="auth-wrap">
  <div class="auth-box">
    <div class="auth-card">
      <div class="auth-logo">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><line x1="6" y1="6.5" x2="6.01" y2="6.5"/><line x1="6" y1="17.5" x2="6.01" y2="17.5"/></svg>
      </div>
      <h2>创建账户</h2>
      <p class="auth-sub">注册即享云服务器选购，60 秒极速开通</p>
      <form method="post" action="/register">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>用户名（4-20 位字母/数字/下划线）</label>
          <input class="form-control" name="username" required pattern="[a-zA-Z0-9_]{4,20}" placeholder="设置登录用户名">
        </div>
        <div class="form-group">
          <label>邮箱（可选）</label>
          <input class="form-control" type="email" name="email" placeholder="用于找回密码">
        </div>
        <div class="form-group">
          <label>密码（至少 6 位）</label>
          <input class="form-control" type="password" name="password" required minlength="6" placeholder="设置登录密码">
        </div>
        <div class="form-group">
          <label>验证码</label>
          <div style="display:flex;gap:10px;align-items:center">
            <input class="form-control" name="captcha" required maxlength="8" style="flex:1" autocomplete="off" placeholder="输入右侧字符">
            <img src="/captcha" alt="验证码" title="点击刷新" style="height:44px;cursor:pointer;border:1.5px solid var(--border);border-radius:10px" onclick="this.src='/captcha?'+Date.now()">
          </div>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit" style="margin-top:6px">注 册</button>
        <p style="margin-top:20px;text-align:center;font-size:14px;color:var(--muted)">已有账号？<a href="/login" style="font-weight:700">去登录</a></p>
      </form>
    </div>
  </div>
</div>
