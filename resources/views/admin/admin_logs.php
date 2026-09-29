<table class="table">
  <thead><tr><th>ID</th><th>管理员</th><th>操作</th><th>IP</th><th>时间</th></tr></thead>
  <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= (int)$l['id'] ?></td>
        <td><?= e($l['username'] ?? ('#' . (int)$l['admin_id'])) ?></td>
        <td><?= e($l['action']) ?></td>
        <td><?= e($l['ip']) ?></td>
        <td><?= e($l['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $pagination ?>
