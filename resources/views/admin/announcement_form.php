<div class="page-head"><h2><?= e($title) ?></h2><a href="/admin/announcements">返回列表</a></div>
<div class="card" style="max-width:720px">
  <form method="post" action="<?= $a ? '/admin/announcements/' . (int)$a['id'] . '/update' : '/admin/announcements/store' ?>">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>标题</label>
      <input class="form-control" name="title" required maxlength="200" value="<?= e($a['title'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>内容（支持换行）</label>
      <textarea class="form-control" name="content" rows="10" required><?= e($a['content'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="is_pinned" value="1" <?= ($a['is_pinned'] ?? 0) ? 'checked' : '' ?>> 置顶</label>
      &nbsp;&nbsp;
      <label><input type="checkbox" name="status" value="1" <?= ($a['status'] ?? 1) ? 'checked' : '' ?>> 发布（不勾选为草稿）</label>
    </div>
    <button class="btn btn-primary" type="submit">保存</button>
  </form>
</div>
