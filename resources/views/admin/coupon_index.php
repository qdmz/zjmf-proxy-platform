<div class="page-head">
  <h2><?= e($title) ?></h2>
  <a class="btn btn-primary" href="/admin/coupons/create">创建优惠券</a>
</div>
<div class="card">
  <table class="table">
    <thead><tr><th>券码</th><th>名称</th><th>类型</th><th>面值</th><th>门槛</th><th>已用/总量</th><th>有效期</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($list as $c): ?>
      <tr>
        <td><code><?= e($c['code']) ?></code></td>
        <td><?= e($c['name']) ?></td>
        <td><?= $c['type'] === 'percent' ? '折扣' : '代金' ?></td>
        <td><?= $c['type'] === 'percent' ? e($c['value']) . ' 折' : e($c['value']) . ' 元' ?></td>
        <td><?= e($c['min_amount']) ?> 元</td>
        <td><?= (int)$c['used_count'] ?> / <?= (int)$c['max_uses'] === 0 ? '不限' : (int)$c['max_uses'] ?></td>
        <td class="muted"><?= e(substr($c['starts_at'] ?? '-', 0, 10)) ?> ~ <?= e(substr($c['ends_at'] ?? '-', 0, 10)) ?></td>
        <td><?= $c['status'] ? '<span class="badge badge-ok">启用</span>' : '<span class="badge">停用</span>' ?></td>
        <td>
          <a href="/admin/coupons/<?= (int)$c['id'] ?>/usages">记录</a>
          <a href="/admin/coupons/<?= (int)$c['id'] ?>/edit">编辑</a>
          <form method="post" action="/admin/coupons/<?= (int)$c['id'] ?>/delete" style="display:inline" onsubmit="return confirm('确定删除？')">
            <?= csrf_field() ?><button class="link danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="9" class="muted">暂无优惠券</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
