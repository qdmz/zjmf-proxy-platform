<div class="detail-grid">
  <div class="card">
    <h3>实例信息</h3>
    <div class="kv"><span>主机名</span><span><?= e($host['domain']) ?></span></div>
    <div class="kv"><span>用户</span><span><?= e($host['username'] ?? $host['user_id']) ?></span></div>
    <div class="kv"><span>产品</span><span><?= e($host['product_name'] ?? '') ?></span></div>
    <div class="kv"><span>供货商</span><span><?= e($host['provider_name'] ?? '') ?></span></div>
    <div class="kv"><span>上游 HostID</span><span><?= (int)$host['upstream_host_id'] ?></span></div>
    <div class="kv"><span>主IP</span><span><?= e($host['dedicated_ip']) ?></span></div>
    <div class="kv"><span>账号</span><span><?= e($host['username']) ?></span></div>
    <div class="kv"><span>密码</span><span><?= $host['password'] ? e($host['password']) : '—' ?></span></div>
    <div class="kv"><span>操作系统</span><span><?= e($host['os']) ?></span></div>
    <div class="kv"><span>状态</span><span><span class="badge badge-<?= e($host['status']) ?>"><?= e(host_status_name($host['status'])) ?></span></span></div>
    <div class="kv"><span>到期时间</span><span><?= e($host['nextduedate'] ?? '') ?></span></div>
    <form method="post" action="/admin/hosts/<?= (int)$host['id'] ?>/sync" style="margin-top:12px">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-sm" type="submit">从上游同步</button>
    </form>
  </div>
</div>
