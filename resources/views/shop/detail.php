<div class="container section">
  <?php $stock = stock_status($product); ?>
  <div class="detail-grid">
    <div class="card">
      <h2 style="margin-bottom:8px"><?= e($product['name']) ?> <span class="badge <?= $stock['badge'] ?>"><?= e($stock['label']) ?></span></h2>
      <div style="color:var(--muted);font-size:14px;margin-bottom:16px"><?= clean_product_html($product['description']) ?></div>

      <div class="opt-group">
        <div class="opt-title">付费周期</div>
        <div class="opt-list" id="cycles">
          <?php $i = 0; foreach ($prices as $pr): $i++; ?>
            <div class="opt-item<?= $i === 1 ? ' selected' : '' ?>" data-cycle="<?= e($pr['billingcycle']) ?>">
              <?= e(cycle_name($pr['billingcycle'])) ?><small><?= e(money((float)$pr['sale_price'] > 0 ? (float)$pr['sale_price'] : (float)$pr['price'])) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php foreach ($options as $opt): ?>
        <div class="opt-group">
          <div class="opt-title"><?= e($opt['name']) ?></div>
          <?php if ($opt['kind'] === 'qty'): ?>
            <input class="form-control qty-input" style="max-width:160px" type="number"
                   data-option="<?= (int)$opt['id'] ?>"
                   min="<?= (int)$opt['qty_min'] ?>" max="<?= (int)$opt['qty_max'] ?>"
                   value="<?= (int)$opt['qty_min'] ?>" placeholder="数量<?= $opt['unit'] ? '(' . e($opt['unit']) . ')' : '' ?>">
          <?php elseif ($opt['kind'] === 'yesno'): ?>
            <div class="opt-list" data-option="<?= (int)$opt['id'] ?>" data-kind="yesno">
              <div class="opt-item selected" data-sub="0">不需要</div>
              <div class="opt-item" data-sub="1">需要<?= $opt['subs'][0]['display_price'] > 0 ? '<small>+' . e(money($opt['subs'][0]['display_price'])) . '</small>' : '' ?></div>
            </div>
          <?php else: ?>
            <div class="opt-list" data-option="<?= (int)$opt['id'] ?>" data-kind="select">
              <?php foreach ($opt['subs'] as $j => $sub): ?>
                <div class="opt-item<?= $j === 0 ? ' selected' : '' ?>" data-sub="<?= (int)$sub['id'] ?>">
                  <?= e($sub['option_name']) ?><?= $sub['display_price'] > 0 ? '<small>+' . e(money($sub['display_price'])) . '</small>' : '' ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <form method="post" action="/order/create" id="orderForm">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <input type="hidden" name="billingcycle" id="billingcycle" value="<?= e($defaultCycle) ?>">
        <input type="hidden" name="configoption" id="configoption" value="">
        <div class="form-group">
          <label>主机名</label>
          <input class="form-control" name="host" required pattern="[a-zA-Z0-9-]{3,32}" placeholder="如 myserver01">
        </div>
        <div class="form-group">
          <label>初始密码（可留空使用随机密码）</label>
          <input class="form-control" type="password" name="password">
        </div>
        <div class="form-group">
          <label>优惠券码（可选）</label>
          <div style="display:flex;gap:8px">
            <input class="form-control" name="coupon_code" id="couponCode" maxlength="32" style="text-transform:uppercase;flex:1" placeholder="如有优惠券请输入券码">
            <button type="button" class="btn btn-sm" id="couponCheck" style="white-space:nowrap">验证</button>
          </div>
          <div id="couponMsg" style="font-size:13px;margin-top:6px"></div>
        </div>
        <div class="kv"><span>应付金额</span><span style="font-size:20px;color:var(--danger);font-weight:700" id="totalPrice">—</span></div>
        <?php if ($stock['in']): ?>
        <button class="btn btn-primary" type="submit" style="width:100%;margin-top:12px">立即购买</button>
        <?php else: ?>
        <div style="background:#fef2f2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-top:12px;font-size:14px">该产品暂时缺货，补货后可购买。如有需要请联系客服。</div>
        <button class="btn" type="button" disabled style="width:100%;margin-top:12px;opacity:.6">缺货，暂无法购买</button>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <h3>产品说明</h3>
      <div class="kv"><span>产品类型</span><span><?= e($product['type']) ?></span></div>
      <div class="kv"><span>库存状态</span><span><span class="badge <?= $stock['badge'] ?>"><?= e($stock['label']) ?></span></span></div>
      <div class="kv"><span>开通方式</span><span>自动开通</span></div>
      <div class="kv"><span>售后支持</span><span>工单支持</span></div>
    </div>
  </div>
</div>

<script>
function currentCycle() {
  var el = document.querySelector('#cycles .opt-item.selected');
  return el ? el.dataset.cycle : '<?= e($defaultCycle) ?>';
}
function collectConfig() {
  var cfg = {};
  document.querySelectorAll('.opt-list[data-option]').forEach(function (g) {
    var sel = g.querySelector('.opt-item.selected');
    cfg[g.dataset.option] = sel ? sel.dataset.sub : '';
  });
  document.querySelectorAll('.qty-input[data-option]').forEach(function (inp) {
    cfg[inp.dataset.option] = inp.value;
  });
  return cfg;
}
function quote() {
  var configoption = collectConfig();
  document.getElementById('billingcycle').value = currentCycle();
  document.getElementById('configoption').value = JSON.stringify(configoption);
  var fd = new FormData();
  fd.append('product_id', '<?= (int)$product['id'] ?>');
  fd.append('billingcycle', currentCycle());
  fd.append('_token', '<?= csrf_token() ?>');
  for (var k in configoption) fd.append('configoption[' + k + ']', configoption[k]);
  fetch('/shop/quote', { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (j.code === 0) {
        document.getElementById('totalPrice').textContent = j.data.total + ' 元';
      }
    });
}
document.getElementById('couponCheck').addEventListener('click', function () {
  var code = document.getElementById('couponCode').value.trim();
  var msg = document.getElementById('couponMsg');
  if (!code) { msg.style.color = 'var(--danger)'; msg.textContent = '请先输入优惠券码'; return; }
  msg.style.color = 'var(--muted)'; msg.textContent = '验证中…';
  var fd = new FormData();
  fd.append('coupon_code', code);
  fd.append('product_id', '<?= (int)$product['id'] ?>');
  fd.append('billingcycle', currentCycle());
  fetch('/order/check-coupon', { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (j.code === 0) {
        msg.style.color = 'var(--success)';
        msg.textContent = j.message + '，实付 ' + j.data.final + ' 元';
        document.getElementById('totalPrice').textContent = j.data.final + ' 元（已优惠）';
      } else {
        msg.style.color = 'var(--danger)';
        msg.textContent = j.message || '验证失败';
      }
    })
    .catch(function () { msg.style.color = 'var(--danger)'; msg.textContent = '网络异常，请稍后重试'; });
});
document.querySelectorAll('#cycles .opt-item').forEach(function (el) {
  el.addEventListener('click', function () {
    document.querySelectorAll('#cycles .opt-item').forEach(function (x) { x.classList.remove('selected'); });
    el.classList.add('selected');
    quote();
  });
});
document.querySelectorAll('.opt-list[data-kind] .opt-item').forEach(function (el) {
  el.addEventListener('click', function (ev) {
    ev.stopPropagation();
    var group = el.closest('.opt-list');
    group.querySelectorAll('.opt-item').forEach(function (x) { x.classList.remove('selected'); });
    el.classList.add('selected');
    quote();
  }, true);
});
document.querySelectorAll('.qty-input').forEach(function (inp) {
  inp.addEventListener('change', quote);
});
quote();
</script>
