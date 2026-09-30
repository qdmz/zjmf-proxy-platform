<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? '管理后台') ?> - <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=2.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
<body>
<div class="admin-layout">
  <aside class="sidebar" id="adminSidebar">
    <div class="brand2">
      <span class="logo">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/></svg>
      </span><?= e(setting('site_name')) ?> · 管理
    </div>
    <nav>
      <?php $uri = $_SERVER['REQUEST_URI'] ?? ''; $isActive = function($p) use ($uri) { return $uri === $p ? 'active' : ''; }; ?>
      <a href="/admin" class="<?= $isActive('/admin') ?>">📊 仪表盘</a>
      <div class="nav-sec">上游与产品</div>
      <a href="/admin/upstream">🔌 上游供货商</a>
      <a href="/admin/upstream/logs">📋 上游接口日志</a>
      <a href="/admin/products">📦 产品管理</a>
      <div class="nav-sec">业务</div>
      <a href="/admin/orders">🧾 订单管理</a>
      <a href="/admin/hosts">🖥️ 实例管理</a>
      <a href="/admin/finance/bills">💳 账单记录</a>
      <a href="/admin/users">👥 用户管理</a>
      <a href="/admin/tickets">🎫 工单管理</a>
      <div class="nav-sec">内容</div>
      <a href="/admin/announcements">📢 公告管理</a>
      <a href="/admin/faqs">❓ 常见问题</a>
      <a href="/admin/coupons">🎟️ 优惠券</a>
      <div class="nav-sec">系统</div>
      <a href="/admin/settings">⚙️ 系统设置</a>
      <a href="/admin/logs">📝 管理员日志</a>
      <a href="/" target="_blank">🌐 前台首页</a>
      <a href="/admin/logout">🚪 退出登录</a>
    </nav>
  </aside>
  <div class="admin-main">
    <div class="admin-topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="nav-toggle" id="adminNavToggle" aria-label="菜单" style="display:none"><span></span><span></span><span></span></button>
        <h2><?= e($title ?? '') ?></h2>
      </div>
      <div class="at-right">
        <span><?= date('Y-m-d') ?> · <?= e(setting('site_name')) ?></span>
      </div>
    </div>
    <div class="admin-content">
      <?php if ($flash = flash('error')): ?><div class="alert alert-error"><?= e($flash) ?></div><?php endif; ?>
      <?php if ($flash = flash('success')): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
      <?= $content ?? '' ?>
    </div>
  </div>
</div>
<script src="/assets/js/app.js"></script>
<script>
(function(){
  var tgl = document.getElementById('adminNavToggle');
  function sync(){ tgl.style.display = window.innerWidth <= 760 ? 'block' : 'none'; }
  sync(); window.addEventListener('resize', sync);
  tgl.addEventListener('click', function(){ document.getElementById('adminSidebar').classList.toggle('open'); });
})();
</script>
</body>
</html>
