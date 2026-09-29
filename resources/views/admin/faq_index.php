<div class="page-head">
  <h2><?= e($title) ?></h2>
  <a class="btn btn-primary" href="/admin/faqs/create">添加问题</a>
</div>
<div class="card">
  <table class="table">
    <thead><tr><th>ID</th><th>分类</th><th>问题</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($list as $f): ?>
      <tr>
        <td><?= (int)$f['id'] ?></td>
        <td><?= e($f['category']) ?></td>
        <td><?= e($f['question']) ?></td>
        <td><?= (int)$f['sort'] ?></td>
        <td><?= $f['status'] ? '<span class="badge badge-ok">显示</span>' : '<span class="badge">隐藏</span>' ?></td>
        <td>
          <a href="/admin/faqs/<?= (int)$f['id'] ?>/edit">编辑</a>
          <form method="post" action="/admin/faqs/<?= (int)$f['id'] ?>/delete" style="display:inline" onsubmit="return confirm('确定删除？')">
            <?= csrf_field() ?><button class="link danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="6" class="muted">暂无</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
