<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? setting('site_name')) ?> - <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=2.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
<body>
<header class="navbar">
  <div class="container navbar-inner">
    <a class="brand" href="/">
      <span class="logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><line x1="6" y1="6.5" x2="6.01" y2="6.5"/><line x1="6" y1="17.5" x2="6.01" y2="17.5"/></svg>
      </span><?= e(setting('site_name')) ?>
    </a>
    <button class="nav-toggle" id="navToggle" aria-label="菜单"><span></span><span></span><span></span></button>
    <nav class="nav-links" id="navLinks">
      <a href="/" class="<?= ($_SERVER['REQUEST_URI'] ?? '/') === '/' ? 'active' : '' ?>">首页</a>
      <a href="/shop">云服务器</a>
      <a href="/announcements">公告</a>
      <a href="/faq">常见问题</a>
      <a href="/console">控制台</a>
      <a href="/orders">我的订单</a>
      <a href="/tickets">工单</a>
    </nav>
    <div class="nav-user">
      <?php if ($user ?? null): ?>
        <a href="/recharge" class="balance">💰 <?= e($user['balance'] ?? '0.00') ?></a>
        <a href="/messages" class="msg">消息<?= (($user['unread'] ?? 0) > 0) ? '(' . ($user['unread']) . ')' : '' ?></a>
        <span class="username"><?= e($user['username']) ?></span>
        <a href="/console/profile">个人资料</a>
        <a href="/logout">退出</a>
      <?php else: ?>
        <a href="/login">登录</a>
        <a href="/register" class="btn btn-primary btn-sm">免费注册</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if ($flash = flash('error')): ?>
<div class="container"><div class="alert alert-error"><?= e($flash) ?></div></div>
<?php endif; ?>
<?php if ($flash = flash('success')): ?>
<div class="container"><div class="alert alert-success"><?= e($flash) ?></div></div>
<?php endif; ?>

<main><?= $content ?? '' ?></main>

<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="f-brand">
          <span class="logo" style="width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:inline-flex;align-items:center;justify-content:center">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/></svg>
          </span><?= e(setting('site_name')) ?>
        </div>
        <p class="f-desc">稳定高速的云服务器销售平台，即开即用、弹性扩展，为您的业务保驾护航。</p>
      </div>
      <div>
        <h5>产品服务</h5>
        <ul>
          <li><a href="/shop">云服务器</a></li>
          <li><a href="/console">控制台</a></li>
          <li><a href="/orders">我的订单</a></li>
          <li><a href="/recharge">余额充值</a></li>
        </ul>
      </div>
      <div>
        <h5>帮助支持</h5>
        <ul>
          <li><a href="/faq">常见问题</a></li>
          <li><a href="/announcements">官方公告</a></li>
          <li><a href="/tickets">提交工单</a></li>
        </ul>
      </div>
      <div>
        <h5>账户</h5>
        <ul>
          <li><a href="/login">登录</a></li>
          <li><a href="/register">注册</a></li>
          <li><a href="/console/profile">个人资料</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> <?= e(setting('site_name')) ?> · <?= e(setting('site_icp') ?? '') ?></p>
    </div>
  </div>
</footer>
<script src="/assets/js/app.js?v=1.3.1"></script>
<script>
document.getElementById('navToggle').addEventListener('click', function () {
  document.getElementById('navLinks').classList.toggle('open');
});
</script>
<!-- 在线客服小部件 -->
<div id="chatFab" title="在线客服">💬</div>
<div id="chatPanel" style="display:none">
  <div class="chat-head">
    <span>🤖 智能客服</span>
    <div>
      <button id="chatTransfer" class="btn btn-sm" type="button">转人工</button>
      <button id="chatClose" class="btn btn-sm" type="button">✕</button>
    </div>
  </div>
  <div id="chatBody" class="chat-body"></div>
  <div class="chat-input">
    <input id="chatMsg" type="text" maxlength="500" placeholder="输入您的问题…" autocomplete="off">
    <button id="chatSend" class="btn btn-primary btn-sm" type="button">发送</button>
  </div>
</div>
<script>
(function(){
  var fab=document.getElementById('chatFab'), panel=document.getElementById('chatPanel'),
      body=document.getElementById('chatBody'), input=document.getElementById('chatMsg'),
      csrf=document.querySelector('meta[name="csrf-token"]').content;
  function addMsg(text, who){
    var d=document.createElement('div');
    d.className='chat-msg '+who;
    d.textContent=text;
    body.appendChild(d);
    body.scrollTop=body.scrollHeight;
  }
  fab.onclick=function(){
    panel.style.display=panel.style.display==='none'?'flex':'none';
    if(panel.style.display!=='none'&&!body.dataset.welcomed){
      body.dataset.welcomed='1';
      addMsg('您好，我是智能客服助手，有什么可以帮您？可直接提问，或点击「转人工」联系客服。','bot');
    }
    if(panel.style.display!=='none') input.focus();
  };
  document.getElementById('chatClose').onclick=function(){panel.style.display='none';};
  function send(){
    var text=input.value.trim();
    if(!text) return;
    input.value='';
    addMsg(text,'user');
    // 不发送 X-CSRF-TOKEN 自定义头，避免触发主机 WAF
    fetch('/chat/send',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'_csrf='+encodeURIComponent(csrf)+'&message='+encodeURIComponent(text)})
      .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
      .then(function(j){
        if(j.code===0){addMsg(j.data.reply,'bot');}
        else{addMsg(j.message||'发送失败','bot');}
      })
      .catch(function(e){addMsg('网络异常('+(e.message||'未知错误')+')，请稍后重试','bot');});
  }
  document.getElementById('chatSend').onclick=send;
  input.addEventListener('keydown',function(e){if(e.key==='Enter')send();});
  document.getElementById('chatTransfer').onclick=function(){
    if(!confirm('确定转接人工客服吗？当前会话将生成工单。'))return;
    fetch('/chat/transfer',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':csrf},
      body:'_csrf='+encodeURIComponent(csrf)})
      .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
      .then(function(j){
        if(j.code===0){addMsg(j.message+' 可在「工单」中查看回复。','bot');}
        else if(j.data&&j.data.login){addMsg('转人工需要先登录，','bot');setTimeout(function(){location.href='/login?next='+encodeURIComponent(location.pathname);},800);}
        else{addMsg(j.message||'转接失败','bot');}
      })
      .catch(function(e){addMsg('网络异常('+(e.message||'未知错误')+')，请稍后重试','bot');});
  };
})();
</script>
</body>
</html>
