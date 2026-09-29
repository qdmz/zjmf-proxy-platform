<div class="container">
  <div class="auth-box">
    <div class="card">
      <h2>用户注册</h2>
      <form method="post" action="/register">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>用户名（4-20 位字母/数字/下划线）</label>
          <input class="form-control" name="username" required pattern="[a-zA-Z0-9_]{4,20}">
        </div>
        <div class="form-group">
          <label>邮箱（可选）</label>
          <input class="form-control" type="email" name="email">
        </div>
        <div class="form-group">
          <label>密码（至少 6 位）</label>
          <input class="form-control" type="password" name="password" required minlength="6">
        </div>
        <div class="form-group">
          <label>验证码</label>
          <div style="display:flex;gap:8px">
            <input class="form-control" name="captcha" required maxlength="8" style="flex:1" autocomplete="off" placeholder="输入右侧字符">
            <img src="/captcha" alt="验证码" title="点击刷新" style="height:38px;cursor:pointer;border:1px solid #ddd;border-radius:4px" onclick="this.src='/captcha?'+Date.now()">
          </div>
        </div>
        <button class="btn btn-primary" style="width:100%" type="submit">注册</button>
        <p style="margin-top:16px;text-align:center;font-size:14px">已有账号？<a href="/login">去登录</a></p>
      </form>
    </div>
  </div>
</div>
