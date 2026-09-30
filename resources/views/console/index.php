<div class="container section">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h2 style="margin:0;text-align:left">控制台</h2>
    <div style="display:flex;gap:8px">
      <a href="/console/profile" class="btn btn-sm">个人资料</a>
      <a href="/console/password" class="btn btn-sm">修改密码</a>
      <a href="/messages" class="btn btn-sm">站内消息</a>
    </div>
  </div>
  <div class="stat-grid">
    <div class="stat"><div class="num"><?= count($hosts) ?></div><div class="label">我的服务器</div></div>
    <div class="stat"><div class="num" style="color:var(--success)">¥<?= e(money((float)$user['balance'])) ?></div><div class="label">账户余额</div></div>
    <div class="stat"><div class="num"><?= count(array_filter($hosts, fn($h) => $h['status'] === 'active')) ?></div><div class="label">运行中</div></div>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin:24px 0 16px">
    <h3 style="margin:0">我的服务器</h3>
    <a href="/shop" class="btn btn-sm btn-primary">+ 购买新服务器</a>
  </div>
  <?php if (empty($hosts)): ?>
    <div class="empty">
      <div style="font-size:48px;margin-bottom:12px">🖥️</div>
      <div>还没有服务器</div>
      <a href="/shop" class="btn btn-primary" style="margin-top:12px">去选购</a>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($hosts as $h): ?>
        <?php
        $statusDot = match($h['status']) {
          'active' => '#16a34a',
          'suspended' => '#dc2626',
          'pending' => '#d97706',
          default => '#64748b',
        };
        ?>
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
            <h3 style="margin:0"><?= e($h['domain']) ?></h3>
            <span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted)">
              <span style="width:8px;height:8px;border-radius:50%;background:<?= $statusDot ?>;display:inline-block"></span>
              <?= e(host_status_name($h['status'])) ?>
            </span>
          </div>
          <div class="meta"><?= e($h['product_name'] ?? '') ?> · <?= e($h['provider_name'] ?? '') ?></div>
          <div class="meta">IP：<?= e($h['dedicated_ip'] ?: '—') ?></div>
          <div class="meta">到期：<?= e($h['nextduedate'] ?? '—') ?></div>
          <a href="/console/host/<?= (int)$h['id'] ?>" class="btn btn-sm btn-primary" style="margin-top:12px;width:100%;text-align:center">管理服务器</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
