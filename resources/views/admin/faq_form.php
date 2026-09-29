<div class="page-head"><h2><?= e($title) ?></h2><a href="/admin/faqs">返回列表</a></div>
<div class="card" style="max-width:720px">
  <form method="post" action="<?= $f ? '/admin/faqs/' . (int)$f['id'] . '/update' : '/admin/faqs/store' ?>">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>分类</label>
      <input class="form-control" name="category" maxlength="50" value="<?= e($f['category'] ?? '常见问题') ?>">
    </div>
    <div class="form-group">
      <label>问题</label>
      <input class="form-control" name="question" required maxlength="255" value="<?= e($f['question'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>答案</label>
      <textarea class="form-control" name="answer" rows="6" required><?= e($f['answer'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>排序（越大越靠前）</label>
      <input class="form-control" name="sort" type="number" value="<?= (int)($f['sort'] ?? 0) ?>">
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="status" value="1" <?= ($f['status'] ?? 1) ? 'checked' : '' ?>> 显示</label>
    </div>
    <button class="btn btn-primary" type="submit">保存</button>
  </form>
</div>
