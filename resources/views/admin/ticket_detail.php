<h3><?= e($ticket['title']) ?> <span class="badge badge-<?= e($ticket['status']) ?>"><?= e(ticket_status_name($ticket['status'])) ?></span></h3>
<p style="color:var(--muted);font-size:13px;margin-bottom:16px">用户：<?= e($ticket['username']) ?></p>
<?php foreach ($replies as $r): ?>
  <div class="reply<?= $r['is_admin'] ? ' admin-reply' : '' ?>">
    <div class="meta"><?= $r['is_admin'] ? '客服' : '用户' ?> · <?= e($r['created_at']) ?></div>
    <div><?= nl2br(e($r['content'])) ?></div>
  </div>
<?php endforeach; ?>
<?php if ($ticket['status'] !== 'closed'): ?>
  <div class="card" style="margin-top:16px;max-width:800px">
    <form method="post" action="/admin/tickets/<?= (int)$ticket['id'] ?>/reply">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>回复内容</label>
        <textarea class="form-control" name="content" rows="4" required></textarea>
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="close" value="1"> 回复后关闭工单</label>
      </div>
      <button class="btn btn-primary" type="submit">发送回复</button>
    </form>
  </div>
<?php endif; ?>
