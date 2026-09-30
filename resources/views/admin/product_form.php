<div class="detail-grid">
  <div class="card">
    <h3>基本信息</h3>
    <form method="post" action="/admin/products/<?= (int)$product['id'] ?>/update">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>产品名称</label>
        <input class="form-control" name="name" value="<?= e($product['name']) ?>" required>
      </div>
      <div class="form-group">
        <label>描述</label>
        <textarea class="form-control" name="description" rows="3"><?= e(html_entity_decode($product['description'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></textarea>
      </div>
      <div class="form-group">
        <label>加价方式</label>
        <div style="display:flex;gap:10px">
          <select class="form-control" name="markup_type">
            <option value="percent" <?= $product['markup_type'] === 'percent' ? 'selected' : '' ?>>百分比</option>
            <option value="fixed" <?= $product['markup_type'] === 'fixed' ? 'selected' : '' ?>>固定金额</option>
          </select>
          <input class="form-control" name="markup_value" type="number" step="0.01" value="<?= e((float)$product['markup_value']) ?>">
        </div>
      </div>
      <div class="form-group">
        <label>状态</label>
        <select class="form-control" name="status">
          <option value="1" <?= $product['status'] ? 'selected' : '' ?>>上架</option>
          <option value="0" <?= $product['status'] ? '' : 'selected' ?>>下架</option>
        </select>
      </div>
      <div class="form-group">
        <label>排序（越大越靠前）</label>
        <input class="form-control" name="sort" type="number" value="<?= (int)$product['sort'] ?>">
      </div>
      <div class="form-group">
        <label>分组</label>
        <input class="form-control" name="group_name" maxlength="50" value="<?= e($product['group_name'] ?? '') ?>" placeholder="如 香港云 / 美国特惠（留空=未分组）">
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="reapply_markup" value="1"> 按新加价策略重新计算所有售价（覆盖手动调价）</label>
      </div>
      <button class="btn btn-primary" type="submit">保存</button>
    </form>
  </div>
  <div class="card">
    <h3>周期价格</h3>
    <form method="post" action="/admin/products/<?= (int)$product['id'] ?>/update">
      <?= csrf_field() ?>
      <table class="table">
        <thead><tr><th>周期</th><th>上游价</th><th>销售价</th><th>设置费</th></tr></thead>
        <tbody>
          <?php foreach ($prices as $pr): ?>
            <tr>
              <td><?= e(cycle_name($pr['billingcycle'])) ?></td>
              <td><?= e(money((float)$pr['upstream_price'])) ?></td>
              <td><input class="form-control" style="max-width:110px" name="prices[<?= (int)$pr['id'] ?>][price]" type="number" step="0.01" value="<?= e((float)$pr['price']) ?>"></td>
              <td><input class="form-control" style="max-width:90px" name="prices[<?= (int)$pr['id'] ?>][setup_fee]" type="number" step="0.01" value="<?= e((float)$pr['setup_fee']) ?>"></td>
              <input type="hidden" name="prices[<?= (int)$pr['id'] ?>][sale_price]" value="<?= e((float)$pr['sale_price']) ?>">
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <input type="hidden" name="name" value="<?= e($product['name']) ?>">
      <button class="btn btn-primary btn-sm" type="submit" style="margin-top:8px">保存价格</button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:24px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h3>可配置项 <small class="muted">（操作系统、数据盘、CPU/内存等从上游同步）</small></h3>
    <form method="post" action="/admin/products/<?= (int)$product['id'] ?>/resync" style="margin:0">
      <?= csrf_field() ?>
      <button class="btn btn-sm" type="submit" onclick="return confirm('从上游重新拉取该产品的可配置项？')">从上游同步配置项</button>
    </form>
  </div>
  <?php if (empty($options)): ?>
    <div class="empty">暂无配置项，点击右上按钮从上游同步</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>配置项</th><th>类型</th><th>可选值</th></tr></thead>
      <tbody>
        <?php foreach ($options as $opt): ?>
          <?php $subs = DB::all("SELECT * FROM `product_config_subs` WHERE `option_id` = ?", [(int)$opt['id']]); ?>
          <tr>
            <td><?= e($opt['name']) ?> <small class="muted">#<?= (int)$opt['upstream_option_id'] ?></small></td>
            <td><?= e(option_type_name((int)$opt['option_type'])) ?></td>
            <td>
              <?php if (in_array((int)$opt['option_type'], [4, 7, 9, 11, 14], true)): ?>
                <span class="muted">数量型（<?= (int)$opt['qty_min'] ?>–<?= (int)$opt['qty_max'] ?><?= e($opt['unit']) ?>）</span>
              <?php else: ?>
                <?= e(implode('、', array_map(fn($s) => $s['option_name'], $subs))) ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
