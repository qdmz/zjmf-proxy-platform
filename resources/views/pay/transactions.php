<div class="container section">
  <h2>余额明细</h2>
  <div class="card" style="margin-bottom:16px">
    <div style="font-size:28px;font-weight:700;color:var(--success)"><?= e(money((float)$user['balance'])) ?></div>
    <div style="font-size:13px;color:var(--muted)">账户余额</div>
    <a href="/recharge" class="btn btn-primary btn-sm" style="margin-top:8px">充值</a>
  </div>
  <table class="table">
    <thead><tr><th>时间</th><th>类型</th><th>金额</th><th>变动后余额</th><th>备注</th></tr></thead>
    <tbody>
      <?php foreach ($list as $t): ?>
        <tr>
          <td><?= e($t['created_at']) ?></td>
          <td><?= e(tx_type_name($t['type'])) ?></td>
          <td style="color:<?= (float)$t['amount'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= (float)$t['amount'] >= 0 ? '+' : '' ?><?= e(money((float)$t['amount'])) ?></td>
          <td><?= e(money((float)$t['balance_after'])) ?></td>
          <td><?= e($t['remark']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= $pagination ?>
</div>
