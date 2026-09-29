<section class="hero">
  <div class="container">
    <h1>稳定 · 高速 · 高性价比云服务器</h1>
    <p>即开即用，弹性扩展，7×24 小时工单支持</p>
    <a href="/shop" class="btn btn-primary">立即选购</a>
  </div>
</section>

<?php if (!empty($announcements)): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="notice">
      📢 <?php foreach ($announcements as $i => $an): ?><?= $i > 0 ? ' · ' : '' ?><a href="/announcements/<?= (int)$an['id'] ?>"><?= e($an['title']) ?></a><?php endforeach; ?>
      <a href="/announcements" style="margin-left:8px">更多»</a>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <h2>热门产品</h2>
    <?php if (empty($products)): ?>
      <div class="empty">暂无上架产品，敬请期待</div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($products as $p): ?>
          <div class="card">
            <h3><?= e($p['name']) ?></h3>
            <div class="meta"><?= e(mb_substr(strip_tags($p['description'] ?? ''), 0, 60)) ?></div>
            <div class="price">
              <?php if ($p['min_price'] !== null): ?>
                <?= e(money((float)$p['min_price'])) ?><small>/月起</small>
              <?php else: ?>
                <small>询价</small>
              <?php endif; ?>
            </div>
            <div style="margin-top:12px"><a href="/shop/<?= (int)$p['id'] ?>" class="btn btn-primary btn-sm">查看详情</a></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
