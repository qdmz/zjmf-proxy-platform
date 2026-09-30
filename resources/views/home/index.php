<section class="hero">
  <div class="container">
    <div class="hero-badge"><span class="dot"></span> 全场云服务器 · 即开即用</div>
    <h1>稳定 · 高速<br><span class="grad">高性价比云服务器</span></h1>
    <p>即开即用，弹性扩展，按需付费，7×24 小时工单技术支持</p>
    <div class="hero-cta">
      <a href="/shop" class="btn btn-primary btn-lg">立即选购 →</a>
      <a href="/faq" class="btn btn-outline-light btn-lg">常见问题</a>
    </div>
    <div class="hero-stats">
      <div class="hs"><b>99.9%</b><span>服务可用性</span></div>
      <div class="hs"><b>60s</b><span>极速开通</span></div>
      <div class="hs"><b>7×24</b><span>工单支持</span></div>
    </div>
  </div>
</section>

<?php if (!empty($announcements)): ?>
<section class="section" style="padding:28px 0 0">
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
    <h2>🔥 热门产品</h2>
    <p class="section-desc">精选高性价比云服务器，满足建站、开发、业务部署等多种场景</p>
    <?php if (empty($products)): ?>
      <div class="empty">暂无上架产品，敬请期待</div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($products as $p): ?>
          <?php $st = stock_status($p); ?>
          <div class="card product-card">
            <div class="pc-top"></div>
            <div class="pc-body">
              <h3><?= e($p['name']) ?></h3>
              <div style="margin-bottom:10px">
                <span class="badge <?= $st['badge'] ?>"><?= e($st['label']) ?></span><?php if (!empty($p['group_name'])): ?> <span class="badge badge-replied"><?= e($p['group_name']) ?></span><?php endif; ?>
              </div>
              <div class="meta"><?= e(product_text_summary($p['description'] ?? '', 60)) ?></div>
              <div class="price" style="margin-top:auto">
                <?php
                $showPrice = $p['monthly_price'] ?? null;
                $showCycle = '月';
                if ($showPrice === null && $p['min_price'] !== null) {
                    $showPrice = $p['min_price'];
                    $showCycle = str_replace(['付'], '', cycle_name($p['min_cycle'] ?? ''));
                }
                ?>
                <?php if ($showPrice !== null): ?>
                  <?= e(money((float)$showPrice)) ?><small>/<?= e($showCycle) ?>起</small>
                <?php else: ?>
                  <small>询价</small>
                <?php endif; ?>
              </div>
              <div style="margin-top:16px"><a href="/shop/<?= (int)$p['id'] ?>" class="btn btn-primary btn-sm btn-block">查看详情</a></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border)">
  <div class="container">
    <h2>为什么选择我们</h2>
    <p class="section-desc">专业云服务体验，从开通到运维全程无忧</p>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
        <h4>极速开通</h4>
        <p>支付成功后 60 秒内自动开通，即开即用，无需等待</p>
      </div>
      <div class="feature-card">
        <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <h4>稳定可靠</h4>
        <p>优质上游资源，99.9% 服务可用性保障，数据安全无忧</p>
      </div>
      <div class="feature-card">
        <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <h4>性价比高</h4>
        <p>透明定价，多种计费周期可选，优惠券叠加更省钱</p>
      </div>
      <div class="feature-card">
        <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
        <h4>7×24 支持</h4>
        <p>全天候工单技术支持，控制台自助管理，操作便捷</p>
      </div>
    </div>
  </div>
</section>
