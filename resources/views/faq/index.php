<div class="container">
  <h2>常见问题</h2>
  <?php if (!$grouped): ?>
    <div class="card"><p class="muted">暂无内容</p></div>
  <?php else: ?>
    <?php foreach ($grouped as $cat => $items): ?>
      <h3 style="margin:20px 0 8px"><?= e($cat) ?></h3>
      <div class="card faq-list">
        <?php foreach ($items as $f): ?>
          <details class="faq-item">
            <summary><?= e($f['question']) ?></summary>
            <div class="faq-answer"><?= nl2br(e($f['answer'])) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <p style="margin-top:20px" class="muted">没找到答案？<a href="/tickets/create">提交工单</a> 或使用右下角在线客服。</p>
</div>
