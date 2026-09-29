<div class="container section">
  <h2><?= e($ticket['title']) ?> <span class="badge badge-<?= e($ticket['status']) ?>"><?= e(ticket_status_name($ticket['status'])) ?></span></h2>
  <div style="max-width:800px">
    <?php foreach ($replies as $r): ?>
      <div class="reply<?= $r['is_admin'] ? ' admin-reply' : '' ?>">
        <div class="meta"><?= $r['is_admin'] ? '客服' : '我' ?> · <?= e($r['created_at']) ?></div>
        <div><?= nl2br(e($r['content'])) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if ($ticket['status'] !== 'closed'): ?>
      <div class="card" style="margin-top:16px">
        <form method="post" action="/tickets/<?= (int)$ticket['id'] ?>/reply">
          <?= csrf_field() ?>
          <div class="form-group">
            <label>继续回复</label>
            <textarea class="form-control" name="content" rows="4" required></textarea>
          </div>
          <button class="btn btn-primary" type="submit">发送</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
