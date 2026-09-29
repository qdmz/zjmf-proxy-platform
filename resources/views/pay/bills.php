<div class="container section">
  <h2>账单明细</h2>
  <?php if (empty($list)): ?>
    <div class="empty">暂无账单</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>账单号</th><th>标题</th><th>类型</th><th>金额</th><th>状态</th><th>支付方式</th><th>创建时间</th><th>支付时间</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach ($list as $b): ?>
          <tr>
            <td><?= e($b['bill_no']) ?></td>
            <td><?= e($b['title']) ?></td>
            <td><?= e(bill_type_name($b['type'])) ?></td>
            <td><?= e(money((float)$b['amount'])) ?></td>
            <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(bill_status_name($b['status'])) ?></span></td>
            <td><?= e($b['payment'] === 'balance' ? '余额' : ($b['payment'] ?: '-')) ?></td>
            <td><?= e($b['created_at']) ?></td>
            <td><?= e($b['paid_at'] ?: '-') ?></td>
            <td>
              <?php if ($b['status'] === 'unpaid'): ?>
                <a href="/pay/<?= e($b['bill_no']) ?>" class="btn btn-sm btn-primary">去支付</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?= $pagination ?>
  <?php endif; ?>
</div>
