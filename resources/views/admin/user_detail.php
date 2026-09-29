<div class="detail-grid">
  <div class="card">
    <h3>用户信息</h3>
    <div class="kv"><span>用户名</span><span><?= e($u['username']) ?></span></div>
    <div class="kv"><span>邮箱</span><span><?= e($u['email']) ?></span></div>
    <div class="kv"><span>余额</span><span style="font-size:18px;font-weight:700;color:var(--success)"><?= e(money((float)$u['balance'])) ?></span></div>
    <div class="kv"><span>实例数</span><span><?= (int)$stats['hosts'] ?></span></div>
    <div class="kv"><span>订单数</span><span><?= (int)$stats['orders'] ?></span></div>
    <div class="kv"><span>累计消费</span><span><?= e(money($stats['income'])) ?></span></div>
    <div class="kv"><span>注册时间</span><span><?= e($u['created_at']) ?></span></div>
    <?php if ($u['role'] === 'user'): ?>
      <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/toggle" style="margin-top:12px">
        <?= csrf_field() ?>
        <button class="btn btn-sm <?= $u['status'] ? 'btn-danger' : 'btn-primary' ?>" type="submit" onclick="return confirm('确定吗？')"><?= $u['status'] ? '禁用用户' : '启用用户' ?></button>
      </form>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>余额调整</h3>
    <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/adjust">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>金额（正数为加款，负数为扣款）</label>
        <input class="form-control" name="amount" type="number" step="0.01" required placeholder="如 100 或 -50">
      </div>
      <div class="form-group">
        <label>备注</label>
        <input class="form-control" name="remark" placeholder="管理员手动调整">
      </div>
      <button class="btn btn-primary btn-sm" type="submit" onclick="return confirm('确定调整吗？')">确认调整</button>
    </form>
  </div>
</div>
<h3 style="margin:16px 0 8px">最近余额变动</h3>
<table class="table">
  <thead><tr><th>时间</th><th>类型</th><th>金额</th><th>变动后</th><th>备注</th></tr></thead>
  <tbody>
    <?php foreach ($txs as $t): ?>
      <tr>
        <td><?= e($t['created_at']) ?></td>
        <td><?= e(tx_type_name($t['type'])) ?></td>
        <td><?= (float)$t['amount'] >= 0 ? '+' : '' ?><?= e(money((float)$t['amount'])) ?></td>
        <td><?= e(money((float)$t['balance_after'])) ?></td>
        <td><?= e($t['remark']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
