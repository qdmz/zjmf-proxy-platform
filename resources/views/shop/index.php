<div class="container section">
  <h2>产品选购</h2>
  <div class="tabs">
    <a href="/shop" class="<?= $type === '' ? 'active' : '' ?>">全部</a>
    <a href="/shop?type=dcim" class="<?= $type === 'dcim' ? 'active' : '' ?>">物理机</a>
    <a href="/shop?type=cloud" class="<?= $type === 'cloud' ? 'active' : '' ?>">云服务器</a>
    <a href="/shop?type=ssl" class="<?= $type === 'ssl' ? 'active' : '' ?>">SSL证书</a>
    <?php if (!empty($groups)): ?>
      <select onchange="location.href=this.value" style="margin-left:auto;padding:8px 12px;border-radius:8px;border:1px solid var(--border);font-size:14px;background:#fff">
        <option value="/shop<?= $type !== '' ? '?type=' . urlencode($type) : '' ?>">全部分组</option>
        <?php foreach ($groups as $g): ?>
          <?php $q = http_build_query(array_filter(['type' => $type, 'group' => $g])); ?>
          <option value="/shop?<?= $q ?>" <?= $group === $g ? 'selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
  </div>
  <?php if (empty($products)): ?>
    <div class="empty">暂无产品</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <?php $st = stock_status($p); ?>
        <div class="card">
          <h3><?= e($p['name']) ?> <span class="badge <?= $st['badge'] ?>"><?= e($st['label']) ?></span><?php if (!empty($p['group_name'])): ?> <span class="badge badge-replied"><?= e($p['group_name']) ?></span><?php endif; ?></h3>
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
