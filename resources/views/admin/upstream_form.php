<div class="card" style="max-width:640px">
  <form method="post" action="/admin/upstream/save">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $provider ? (int)$provider['id'] : 0 ?>">
    <div class="form-group">
      <label>供货商名称</label>
      <input class="form-control" name="name" required value="<?= e($provider['name'] ?? '') ?>" placeholder="如 主上游A">
    </div>
    <div class="form-group">
      <label>智简魔方 API 地址（不带 /v1）</label>
      <input class="form-control" name="base_url" required value="<?= e($provider['base_url'] ?? '') ?>" placeholder="https://up.example.com">
    </div>
    <div class="form-group">
      <label>API 账号（邮箱）</label>
      <input class="form-control" name="account" required value="<?= e($provider['account'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>API 密码<?= $provider ? '（留空不修改）' : '' ?></label>
      <input class="form-control" type="password" name="password" <?= $provider ? '' : 'required' ?>>
    </div>
    <div class="form-group">
      <label>上游结算支付方式</label>
      <input class="form-control" name="checkout_payment" value="<?= e($provider['checkout_payment'] ?? 'credit') ?>" placeholder="credit">
      <small style="color:var(--muted)">购物车结算时传给上游的 payment 参数；若上游报 Wrong payment method，请换成上游支持的支付网关标识</small>
    </div>
    <div class="form-group">
      <label>状态</label>
      <select class="form-control" name="status">
        <option value="1" <?= ($provider['status'] ?? 1) == 1 ? 'selected' : '' ?>>启用</option>
        <option value="0" <?= ($provider['status'] ?? 1) == 0 ? 'selected' : '' ?>>禁用</option>
      </select>
    </div>
    <div class="form-group">
      <label>备注</label>
      <input class="form-control" name="remark" value="<?= e($provider['remark'] ?? '') ?>">
    </div>
    <button class="btn btn-primary" type="submit">保存</button>
  </form>
</div>
