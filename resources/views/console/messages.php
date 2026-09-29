<div class="container section">
  <h2>站内消息</h2>
  <?php if (empty($list)): ?>
    <div class="empty">暂无消息</div>
  <?php else: ?>
    <?php foreach ($list as $m): ?>
      <div class="reply">
        <div class="meta"><?= e($m['title']) ?> · <?= e($m['created_at']) ?></div>
        <div><?= nl2br(e($m['content'])) ?></div>
      </div>
    <?php endforeach; ?>
    <?= $pagination ?>
  <?php endif; ?>
</div>
