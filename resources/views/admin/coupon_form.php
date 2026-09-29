<div class="page-head"><h2><?= e($title) ?></h2><a href="/admin/coupons">返回列表</a></div>
<div class="card" style="max-width:720px">
  <form method="post" action="<?= $c ? '/admin/coupons/' . (int)$c['id'] . '/update' : '/admin/coupons/store' ?>">
    <?= csrf_field() ?>
    <?php if (!$c): ?>
    <div class="form-group">
      <label>券码（留空自动生成，用户下单时输入）</label>
      <input class="form-control" name="code" maxlength="32" style="text-transform:uppercase" placeholder="如 SAVE20">
    </div>
    <?php else: ?>
    <div class="form-group"><label>券码</label><p><code><?= e($c['code']) ?></code>（不可修改）</p></div>
    <?php endif; ?>
    <div class="form-group">
      <label>名称</label>
      <input class="form-control" name="name" required maxlength="100" value="<?= e($c['name'] ?? '') ?>" placeholder="如 新人立减20元">
    </div>
    <div class="form-group">
      <label>类型</label>
      <select class="form-control" name="type">
        <option value="fixed" <?= ($c['type'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>代金券（固定金额抵扣）</option>
        <option value="percent" <?= ($c['type'] ?? '') === 'percent' ? 'selected' : '' ?>>折扣券（按折扣率，如85=85折）</option>
      </select>
    </div>
    <div class="form-group">
      <label>面值（代金券=抵扣金额；折扣券=折扣率如85）</label>
      <input class="form-control" name="value" type="number" step="0.01" min="0" required value="<?= e($c['value'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>最低订单金额（元，0=无门槛）</label>
      <input class="form-control" name="min_amount" type="number" step="0.01" min="0" value="<?= e($c['min_amount'] ?? 0) ?>">
    </div>
    <div class="form-group">
      <label>总可用次数（0=不限）</label>
      <input class="form-control" name="max_uses" type="number" min="0" value="<?= (int)($c['max_uses'] ?? 0) ?>">
    </div>
    <div class="form-group">
      <label>每用户限用次数</label>
      <input class="form-control" name="per_user_limit" type="number" min="1" value="<?= (int)($c['per_user_limit'] ?? 1) ?>">
    </div>
    <div class="form-group">
      <label>生效时间（留空=立即）</label>
      <input class="form-control" name="starts_at" type="datetime-local" value="<?= e(isset($c['starts_at']) && $c['starts_at'] ? substr(str_replace(' ', 'T', $c['starts_at']), 0, 16) : '') ?>">
    </div>
    <div class="form-group">
      <label>过期时间（留空=长期有效）</label>
      <input class="form-control" name="ends_at" type="datetime-local" value="<?= e(isset($c['ends_at']) && $c['ends_at'] ? substr(str_replace(' ', 'T', $c['ends_at']), 0, 16) : '') ?>">
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="status" value="1" <?= ($c['status'] ?? 1) ? 'checked' : '' ?>> 启用</label>
    </div>
    <button class="btn btn-primary" type="submit">保存</button>
  </form>
</div>
