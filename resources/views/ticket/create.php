<div class="container section">
  <h2>提交工单</h2>
  <div class="card" style="max-width:720px">
    <form method="post" action="/tickets/create">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>标题</label>
        <input class="form-control" name="title" required maxlength="200">
      </div>
      <?php if (!empty($hosts)): ?>
        <div class="form-group">
          <label>关联实例（可选）</label>
          <select class="form-control" name="host_id">
            <option value="0">不关联</option>
            <?php foreach ($hosts as $h): ?>
              <option value="<?= (int)$h['id'] ?>"><?= e($h['domain']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="form-group">
        <label>问题描述</label>
        <textarea class="form-control" name="content" rows="6" required></textarea>
      </div>
      <button class="btn btn-primary" type="submit">提交</button>
    </form>
  </div>
</div>
