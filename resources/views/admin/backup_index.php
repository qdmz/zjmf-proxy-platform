<div class="card" style="margin-bottom:20px">
  <h3>一键备份</h3>
  <p class="muted" style="margin:8px 0 16px">
    备份文件保存在 <code>storage/backups/</code>（已禁止 Web 直接访问）。
    <?php if ($free !== null): ?>当前磁盘剩余空间：<strong><?= e(\App\Services\BackupService::fmtSize($free)) ?></strong><?php endif; ?>
  </p>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <form method="post" action="/admin/backup/db" style="display:inline">
      <?= csrf_field() ?>
      <button class="btn btn-primary" type="submit">🗄️ 备份数据库</button>
    </form>
    <form method="post" action="/admin/backup/files" style="display:inline">
      <?= csrf_field() ?>
      <button class="btn btn-primary" type="submit">📁 备份网站文件</button>
    </form>
  </div>
  <p class="muted" style="margin-top:12px;font-size:13px">
    数据库备份：优先使用 mysqldump，不可用时自动降级为 PHP 逐表导出。<br>
    网站文件备份：打包全部项目文件，自动排除 <code>config/config.php</code>、日志与备份目录自身。
  </p>
</div>

<div class="card" style="margin-bottom:20px">
  <h3>备份列表</h3>
  <?php if (!$backups): ?>
    <p class="muted" style="margin-top:12px">暂无备份，请先执行一键备份。</p>
  <?php else: ?>
  <div class="table-wrap" style="margin-top:12px">
  <table class="table">
    <thead><tr><th>文件名</th><th>类型</th><th>大小</th><th>备份时间</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($backups as $b): ?>
      <tr>
        <td><code><?= e($b['name']) ?></code></td>
        <td><?= $b['type'] === 'db' ? '<span class="badge badge-blue">数据库</span>' : '<span class="badge badge-green">网站文件</span>' ?></td>
        <td><?= e(\App\Services\BackupService::fmtSize($b['size'])) ?></td>
        <td><?= date('Y-m-d H:i:s', $b['mtime']) ?></td>
        <td>
          <a class="btn btn-sm" href="/admin/backup/download/<?= urlencode($b['name']) ?>">下载</a>
          <form method="post" action="/admin/backup/delete" style="display:inline" onsubmit="return confirm('确定删除该备份？此操作不可恢复。')">
            <?= csrf_field() ?>
            <input type="hidden" name="name" value="<?= e($b['name']) ?>">
            <button class="btn btn-sm btn-danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:20px">
  <h3>还原数据库</h3>
  <div class="alert alert-error" style="margin:12px 0">
    ⚠️ 还原会<strong>覆盖当前全部数据</strong>！系统会在还原前自动备份当前数据库（文件名 <code>db_auto_before_restore_*</code>），还原失败会自动回滚。
  </div>
  <form method="post" action="/admin/backup/restore-db" enctype="multipart/form-data" onsubmit="return confirm('确定要还原数据库吗？当前数据将被覆盖（还原前会自动备份）。')">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>选择 SQL 备份文件（.sql，上限 200MB）</label>
      <input type="file" name="sql_file" accept=".sql" class="form-control" required>
    </div>
    <button class="btn btn-danger" type="submit">开始还原数据库</button>
  </form>
</div>

<div class="card">
  <h3>还原网站文件</h3>
  <div class="alert alert-error" style="margin:12px 0">
    ⚠️ 还原会覆盖网站文件！<code>config/config.php</code> 永不被覆盖，备份目录自身也不会被还原。
  </div>
  <form method="post" action="/admin/backup/restore-files" enctype="multipart/form-data" onsubmit="return confirm('确定要还原网站文件吗？当前文件将被覆盖（config.php 除外）。')">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>选择文件备份包（.zip，上限 500MB）</label>
      <input type="file" name="zip_file" accept=".zip" class="form-control" required>
    </div>
    <button class="btn btn-danger" type="submit">开始还原网站文件</button>
  </form>
</div>
