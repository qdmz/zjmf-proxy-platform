<?php $this->extend('layout/app'); ?>
<div class="container" style="padding:32px 0">
  <p><a href="/console/host/<?= (int)$host['id'] ?>">← 返回实例</a></p>
  <h2 style="margin:12px 0 20px">升降级配置 <small class="muted"><?= e($host['domain']) ?></small></h2>

  <?php if ($err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php elseif (empty($options)): ?>
    <div class="empty">该产品暂无可升降级的配置项</div>
  <?php else: ?>
    <div class="card" style="margin-bottom:20px">
      <h3>选择新配置</h3>
      <form id="upgradeForm">
        <?php foreach ($options as $opt): ?>
          <div class="opt-group">
            <div class="opt-title"><?= e($opt['option_name'] ?? $opt['name'] ?? '') ?></div>
            <?php
            $type = (int)($opt['option_type'] ?? 0);
            $key = $opt['id'] ?? '';
            $cur = $opt['value'] ?? '';
            $subs = $opt['sub'] ?? $opt['subs'] ?? [];
            ?>
            <?php if (in_array($type, [4, 7, 9, 11, 14], true)): ?>
              <input type="number" class="form-control opt-input" data-key="<?= e($key) ?>"
                value="<?= e($cur) ?>" min="<?= e($opt['qty_min'] ?? 0) ?>" max="<?= e($opt['qty_max'] ?? 9999) ?>"
                style="max-width:200px" placeholder="当前: <?= e($cur) ?>">
            <?php else: ?>
              <div class="opt-list">
                <?php foreach ($subs as $sub): ?>
                  <label class="opt-item <?= ((string)($sub['id'] ?? '') === (string)$cur) ? 'selected' : '' ?>">
                    <input type="radio" name="opt_<?= e($key) ?>" value="<?= e($sub['id'] ?? '') ?>"
                      class="opt-input" data-key="<?= e($key) ?>"
                      <?= ((string)($sub['id'] ?? '') === (string)$cur) ? 'checked' : '' ?> style="display:none">
                    <?= e($sub['option_name'] ?? $sub['name'] ?? '') ?>
                    <?php if (isset($sub['price'])): ?><small>+<?= e($sub['price']) ?>/周期</small><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <button type="button" class="btn btn-primary" id="btnQuote">计算差价</button>
      </form>
    </div>

    <div class="card" id="quoteBox" style="display:none">
      <h3>差价明细</h3>
      <div id="quoteDetail"></div>
      <form method="post" action="/console/host/<?= (int)$host['id'] ?>/upgrade/checkout" style="margin-top:16px">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary" onclick="return confirm('确认使用余额支付差价并执行升降级？')">确认支付并升降级</button>
      </form>
    </div>

    <script>
    (function () {
      var form = document.getElementById('upgradeForm');
      var quoteBox = document.getElementById('quoteBox');
      var quoteDetail = document.getElementById('quoteDetail');
      form.querySelectorAll('.opt-list .opt-item').forEach(function (el) {
        el.addEventListener('click', function () {
          var name = el.querySelector('input').name;
          form.querySelectorAll('input[name="' + name + '"]').forEach(function (i) {
            i.closest('.opt-item').classList.remove('selected');
          });
          el.classList.add('selected');
        });
      });
      document.getElementById('btnQuote').addEventListener('click', function () {
        var data = new FormData();
        form.querySelectorAll('.opt-input').forEach(function (input) {
          var key = input.dataset.key;
          if (input.type === 'radio') {
            if (input.checked) data.append('configoption[' + key + ']', input.value);
          } else if (input.value !== '') {
            data.append('configoption[' + key + ']', input.value);
          }
        });
        data.append('<?= csrf_token_name() ?>', '<?= csrf_token() ?>');
        quoteDetail.innerHTML = '<div class="empty">计算中…</div>';
        quoteBox.style.display = '';
        fetch('/console/host/<?= (int)$host['id'] ?>/upgrade/quote', {
          method: 'POST', body: data,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) {
            quoteDetail.innerHTML = '<div class="alert alert-error">' + (j.msg || '报价失败') + '</div>';
            return;
          }
          var d = j.data || {};
          var html = '<div class="kv"><span>应付差价</span><span style="color:var(--danger);font-weight:700">¥' + (d.total || d.price || '0.00') + '</span></div>';
          if (d.desc) html += '<div class="muted" style="font-size:13px;margin-top:8px">' + d.desc + '</div>';
          quoteDetail.innerHTML = html;
        }).catch(function () {
          quoteDetail.innerHTML = '<div class="alert alert-error">网络错误</div>';
        });
      });
    })();
    </script>
  <?php endif; ?>
</div>
