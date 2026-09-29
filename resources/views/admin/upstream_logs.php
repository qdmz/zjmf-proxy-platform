<table class="table">
  <thead><tr><th>ID</th><th>供货商</th><th>操作</th><th>请求摘要</th><th>结果</th><th>时间</th></tr></thead>
  <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= (int)$l['id'] ?></td>
        <td><?= e($l['provider_name'] ?? '') ?></td>
        <td><?= e($l['action']) ?></td>
        <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($l['request'] ?? '') ?>"><?= e(mb_substr($l['request'] ?? '', 0, 80)) ?></td>
        <td><?= $l['success'] ? '<span class="badge badge-active">成功</span>' : '<span class="badge badge-failed">失败</span>' ?></td>
        <td><?= e($l['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?= $pagination ?>
