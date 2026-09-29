<div class="container section">
  <h2>控制台</h2>
  <div style="margin-bottom:12px">
    <a href="/console/profile" class="btn btn-sm">个人资料</a>
    <a href="/console/password" class="btn btn-sm">修改密码</a>
    <a href="/messages" class="btn btn-sm">站内消息</a>
  </div>
  <div class="stat-grid">
    <div class="stat"><div class="num"><?= count($hosts) ?></div><div class="label">我的服务器</div></div>
    <div class="stat"><div class="num"><?= e(money((float)$user['balance'])) ?></div><div class="label">账户余额</div></div>
  </div>
  <h3 style="margin-bottom:12px">我的服务器</h3>
  <?php if (empty($hosts)): ?>
    <div class="empty">还没有服务器，<a href="/shop">去选购</a></div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($hosts as $h): ?>
        <div class="card">
          <h3><?= e($h['domain']) ?></h3>
          <div class="meta"><?= e($h['product_name'] ?? '') ?> · <?= e($h['provider_name'] ?? '') ?></div>
          <div class="meta">IP：<?= e($h['dedicated_ip']) ?> · 到期：<?= e($h['nextduedate'] ?? '—') ?></div>
          <div style="margin:8px 0"><span class="badge badge-<?= e($h['status']) ?>"><?= e(host_status_name($h['status'])) ?></span></div>
          <a href="/console/host/<?= (int)$h['id'] ?>" class="btn btn-sm">管理</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
