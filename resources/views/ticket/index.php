<div class="container section">
  <h2>我的工单</h2>
  <div style="margin-bottom:16px"><a href="/tickets/create" class="btn btn-primary btn-sm">提交工单</a></div>
  <?php if (empty($tickets)): ?>
    <div class="empty">暂无工单</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>标题</th><th>状态</th><th>更新时间</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td><?= e($t['title']) ?></td>
            <td><span class="badge badge-<?= e($t['status']) ?>"><?= e(ticket_status_name($t['status'])) ?></span></td>
            <td><?= e($t['updated_at']) ?></td>
            <td><a href="/tickets/<?= (int)$t['id'] ?>" class="btn btn-sm">查看</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
