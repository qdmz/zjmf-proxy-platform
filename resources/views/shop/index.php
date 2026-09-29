<div class="container section">
  <h2>产品选购</h2>
  <div class="tabs">
    <a href="/shop" class="<?= $type === '' ? 'active' : '' ?>">全部</a>
    <a href="/shop?type=dcim" class="<?= $type === 'dcim' ? 'active' : '' ?>">物理机</a>
    <a href="/shop?type=cloud" class="<?= $type === 'cloud' ? 'active' : '' ?>">云服务器</a>
    <a href="/shop?type=ssl" class="<?= $type === 'ssl' ? 'active' : '' ?>">SSL证书</a>
  </div>
  <?php if (empty($products)): ?>
    <div class="empty">暂无产品</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <?php $st = stock_status($p); ?>
        <div class="card">
          <h3><?= e($p['name']) ?> <span class="badge <?= $st['badge'] ?>"><?= e($st['label']) ?></span></h3>
          <div class="meta"><?= e(product_text_summary($p['description'] ?? '', 80)) ?></div>
          <div class="price">
            <?php if ($p['min_price'] !== null): ?><?= e(money((float)$p['min_price'])) ?><small>/月起</small><?php else: ?><small>询价</small><?php endif; ?>
          </div>
          <div style="margin-top:12px"><a href="/shop/<?= (int)$p['id'] ?>" class="btn btn-primary btn-sm">购买</a></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
