<div class="container">
  <h2>公告</h2>
  <div class="card">
    <?php if (!$list): ?>
      <p class="muted">暂无公告</p>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($list as $a): ?>
          <li class="list-item">
            <a href="/announcements/<?= (int)$a['id'] ?>">
              <?php if ($a['is_pinned']): ?><span class="badge badge-hot">置顶</span><?php endif; ?>
              <?= e($a['title']) ?>
            </a>
            <span class="muted"><?= e(substr($a['published_at'] ?? $a['created_at'], 0, 10)) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?= $pagination ?? '' ?>
</div>
