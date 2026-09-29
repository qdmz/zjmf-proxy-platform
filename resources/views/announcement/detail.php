<div class="container section">
  <div class="card article">
    <h2><?= e($a['title']) ?></h2>
    <p class="muted"><?= e(substr($a['published_at'] ?? $a['created_at'], 0, 16)) ?></p>
    <hr>
    <div class="article-body"><?= nl2br(e($a['content'])) ?></div>
    <p style="margin-top:24px"><a href="/announcements">← 返回公告列表</a></p>
  </div>
</div>
