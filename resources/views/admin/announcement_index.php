<div class="page-head">
  <h2><?= e($title) ?></h2>
  <a class="btn btn-primary" href="/admin/announcements/create">发布公告</a>
</div>
<div class="card">
  <table class="table">
    <thead><tr><th>ID</th><th>标题</th><th>置顶</th><th>状态</th><th>发布时间</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($list as $a): ?>
      <tr>
        <td><?= (int)$a['id'] ?></td>
        <td><a href="/announcements/<?= (int)$a['id'] ?>" target="_blank"><?= e($a['title']) ?></a></td>
        <td><?= $a['is_pinned'] ? '是' : '否' ?></td>
        <td><?= $a['status'] ? '<span class="badge badge-ok">已发布</span>' : '<span class="badge">草稿</span>' ?></td>
        <td><?= e(substr($a['published_at'] ?? $a['created_at'], 0, 16)) ?></td>
        <td>
          <a href="/admin/announcements/<?= (int)$a['id'] ?>/edit">编辑</a>
          <form method="post" action="/admin/announcements/<?= (int)$a['id'] ?>/delete" style="display:inline" onsubmit="return confirm('确定删除？')">
            <?= csrf_field() ?><button class="link danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="6" class="muted">暂无公告</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= $pagination ?? '' ?>
