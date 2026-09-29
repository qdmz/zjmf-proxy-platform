<div class="container">
  <div class="auth-box">
    <div class="card">
      <h2>忘记密码</h2>
      <p class="muted">输入注册时填写的邮箱，我们将发送密码重置链接（1 小时内有效）。</p>
      <form method="post" action="/forgot">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>注册邮箱</label>
          <input class="form-control" type="email" name="email" required>
        </div>
        <div class="form-group">
          <label>验证码</label>
          <div style="display:flex;gap:8px">
            <input class="form-control" name="captcha" required maxlength="8" style="flex:1" autocomplete="off" placeholder="输入右侧字符">
            <img src="/captcha" alt="验证码" title="点击刷新" style="height:38px;cursor:pointer;border:1px solid #ddd;border-radius:4px" onclick="this.src='/captcha?'+Date.now()">
          </div>
        </div>
        <button class="btn btn-primary" style="width:100%" type="submit">发送重置邮件</button>
        <p style="margin-top:16px;text-align:center;font-size:14px"><a href="/login">返回登录</a></p>
      </form>
    </div>
  </div>
</div>
