<h2>上游接口日志 #<?= (int)$log['id'] ?></h2>
<div class="kv"><span>供货商</span><span><?= e($log['provider_name'] ?? '') ?></span></div>
<div class="kv"><span>操作</span><span><?= e($log['action'] ?? '') ?></span></div>
<div class="kv"><span>主机ID</span><span><?= (int)($log['host_id'] ?? 0) ?></span></div>
<div class="kv"><span>结果</span><span><?= $log['success'] ? '成功' : '失败' ?></span></div>
<div class="kv"><span>时间</span><span><?= e($log['created_at'] ?? '') ?></span></div>

<h3 style="margin-top:16px">请求</h3>
<pre style="background:#f5f5f5;padding:12px;overflow:auto;max-height:300px;white-space:pre-wrap;word-break:break-all"><?= e($log['request'] ?? '') ?></pre>

<h3 style="margin-top:16px">返回</h3>
<pre style="background:#f5f5f5;padding:12px;overflow:auto;max-height:600px;white-space:pre-wrap;word-break:break-all"><?= e($log['response'] ?? '') ?></pre>

<p style="margin-top:16px"><a href="/admin/upstream/logs">← 返回日志列表</a></p>
