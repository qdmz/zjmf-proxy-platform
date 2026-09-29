<div class="tabs">
  <?php foreach ($groups as $k => $v): ?>
    <a href="/admin/settings?group=<?= e($k) ?>" class="<?= $group === $k ? 'active' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>
<div class="card" style="max-width:680px">
  <form method="post" action="/admin/settings/save">
    <?= csrf_field() ?>
    <input type="hidden" name="group" value="<?= e($group) ?>">
    <?php foreach ($fields as $key => $def): ?>
      <div class="form-group">
        <label><?= e($def[0]) ?>（<?= e($key) ?>）</label>
        <?php if ($def[1] === 'bool'): ?>
          <label><input type="checkbox" name="<?= e($key) ?>" value="1" <?= ($settings[$key] ?? '0') === '1' ? 'checked' : '' ?>> 启用</label>
        <?php else: ?>
          <?php $isPass = ($def[1] === 'password' || $def[1] === 'password_enc'); ?>
          <input class="form-control" name="<?= e($key) ?>" type="<?= $isPass ? 'password' : 'text' ?>" value="<?= $isPass ? '' : e($settings[$key] ?? '') ?>" placeholder="<?= $isPass ? '留空不修改' : '' ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit">保存设置</button>
  </form>
</div>

<?php if ($group === 'mail'): ?>
<div class="card" style="max-width:680px;margin-top:16px">
  <h3>发送测试邮件</h3>
  <form method="post" action="/admin/settings/test-mail">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>收件邮箱</label>
      <input class="form-control" name="test_email" type="email" placeholder="test@example.com" required>
    </div>
    <button class="btn" type="submit">发送测试邮件</button>
  </form>
  <p class="muted" style="margin-top:8px">常用 SMTP：QQ 邮箱 smtp.qq.com:587(tls)；163 邮箱 smtp.163.com:587(tls)；Gmail smtp.gmail.com:587(tls)。密码请使用邮箱授权码。</p>
</div>
<?php endif; ?>
