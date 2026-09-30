<div class="container section">
  <h2><?= e($host['domain']) ?> <span class="badge badge-<?= e($host['status']) ?>"><?= e(host_status_name($host['status'])) ?></span></h2>

  <div class="detail-grid">
    <div class="card">
      <h3>服务器控制</h3>
      <div class="action-btns" id="actionBtns">
        <?php
        // 从上游能力清单动态渲染按钮
        $btnMap = [];
        foreach (($caps['button'] ?? []) as $b) {
            $btnMap[$b['func']] = $b['name'];
        }
        $order = ['on', 'off', 'reboot', 'hard_off', 'hard_reboot', 'rescue', 'repassword', 'reinstall', 'vnc'];
        foreach ($order as $func):
            if (!isset($btnMap[$func])) continue;
            $label = $actions[$func] ?? $btnMap[$func];
        ?>
          <button class="btn btn-sm host-action" data-func="<?= e($func) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
        <?php if (empty($btnMap)): ?>
          <button class="btn btn-sm host-action" data-func="on">开机</button>
          <button class="btn btn-sm host-action" data-func="off">关机</button>
          <button class="btn btn-sm host-action" data-func="reboot">重启</button>
          <button class="btn btn-sm host-action" data-func="vnc">VNC</button>
        <?php endif; ?>
        <button class="btn btn-sm" id="btnPower">刷新电源状态</button>
      </div>
      <div id="actionMsg" style="font-size:14px;margin-bottom:12px"></div>

      <div class="card" style="background:#f8fafc;margin-top:16px" id="repasswordBox" hidden>
        <h3>重置密码</h3>
        <div class="form-group"><input class="form-control" id="newPassword" type="password" placeholder="新密码"></div>
        <button class="btn btn-primary btn-sm" id="btnDoRepassword">确认重置</button>
      </div>

      <div class="card" style="background:#f8fafc;margin-top:16px" id="reinstallBox" hidden>
        <h3>重装系统</h3>
        <div class="form-group">
          <label>选择系统</label>
          <select class="form-control" id="osSelect"><option>加载中...</option></select>
        </div>
        <div class="notice">重装将清空所有数据，请确认已备份！</div>
        <button class="btn btn-danger btn-sm" id="btnDoReinstall">确认重装</button>
      </div>

      <div class="card" style="background:#f8fafc;margin-top:16px" id="vncBox" hidden>
        <h3>VNC 控制台</h3>
        <a id="vncLink" href="#" target="_blank" class="btn btn-primary btn-sm">打开 VNC 控制台</a>
      </div>

      <h3 style="margin-top:24px">续费</h3>
      <form method="post" action="/console/host/<?= (int)$host['id'] ?>/renew" style="display:flex;gap:10px;align-items:center">
        <?= csrf_field() ?>
        <select class="form-control" name="billingcycle" style="max-width:220px">
          <?php foreach ($prices as $pr): ?>
            <option value="<?= e($pr['billingcycle']) ?>"><?= e(cycle_name($pr['billingcycle'])) ?> - <?= e(money((float)$pr['sale_price'])) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">生成续费账单</button>
      </form>

      <h3 style="margin-top:24px">退订</h3>
      <form method="post" action="/console/host/<?= (int)$host['id'] ?>/cancel" onsubmit="return confirm('确定要退订吗？')">
        <?= csrf_field() ?>
        <select class="form-control" name="type" style="max-width:220px;margin-bottom:8px">
          <option value="Endofbilling">到期删除</option>
          <option value="Immediate">立即删除（数据不可恢复）</option>
        </select>
        <button class="btn btn-danger btn-sm" type="submit">申请退订</button>
      </form>
    </div>

    <div class="card">
      <h3>实例信息</h3>
      <div class="kv"><span>产品</span><span><?= e($host['product_name'] ?? '') ?></span></div>
      <div class="kv"><span>主机名</span><span><?= e($host['domain']) ?></span></div>
      <div class="kv"><span>主IP</span><span><?= e($host['dedicated_ip'] ?: '—') ?></span></div>
      <?php if (!empty($host['assigned_ips_arr'])): ?>
        <div class="kv"><span>附加IP</span><span><?= e(implode(', ', $host['assigned_ips_arr'])) ?></span></div>
      <?php endif; ?>
      <div class="kv"><span>用户名</span><span><?= e($host['username'] ?: '—') ?></span></div>
      <div class="kv"><span>密码</span><span><?= $host['password'] ? e($host['password']) : '—' ?></span></div>
      <div class="kv"><span>操作系统</span><span><?= e($host['os'] ?: '—') ?></span></div>
      <div class="kv"><span>端口</span><span><?= (int)$host['port'] > 0 ? (int)$host['port'] : '—' ?></span></div>
      <div class="kv"><span>带宽</span><span><?= e($host['bwlimit'] ?: '—') ?></span></div>
      <div class="kv"><span>付费周期</span><span><?= e(cycle_name($host['billingcycle'])) ?></span></div>
      <div class="kv"><span>开通时间</span><span><?= e($host['regdate'] ?? '—') ?></span></div>
      <div class="kv"><span>到期时间</span><span><?= e($host['nextduedate'] ?? '—') ?></span></div>
      <div class="kv"><span>自动续费</span><span><?= (int)$host['initiative_renew'] ? '已开启' : '未开启' ?></span></div>
      <?php if (!empty($host['suspend_reason'])): ?>
        <div class="kv"><span>暂停原因</span><span style="color:var(--danger)"><?= e($host['suspend_reason']) ?></span></div>
      <?php endif; ?>
      <?php
      // NAT 信息（上游 module 接口返回）
      $natAcl = $moduleInfo['dcimcloud']['nat_acl'] ?? '';
      $natWeb = $moduleInfo['dcimcloud']['nat_web'] ?? '';
      if ($natAcl): ?>
        <div class="kv"><span>NAT映射</span><span><?= e($natAcl) ?></span></div>
      <?php endif; ?>
      <?php if ($natWeb): ?>
        <div class="kv"><span>共享建站</span><span><?= e($natWeb) ?></span></div>
      <?php endif; ?>
      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
        <a href="/console/host/<?= (int)$host['id'] ?>/upgrade" class="btn btn-sm btn-primary">升降级配置</a>
        <button class="btn btn-sm" id="btnSync">同步实例信息</button>
      </div>
    </div>
  </div>

  <?php
  // 模块自定义标签页：NAT转发、快照、安全组等
  $clientArea = $moduleInfo['module_client_area'] ?? [];
  if (!empty($clientArea)):
  ?>
  <div class="card" style="margin-top:24px">
    <h3>高级管理</h3>
    <div class="tabs" id="moduleTabs">
      <?php foreach ($clientArea as $i => $tab): ?>
        <a href="javascript:void(0)" data-key="<?= e($tab['key']) ?>" class="<?= $i === 0 ? 'active' : '' ?>"><?= e($tab['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div id="moduleTabContent" style="min-height:200px"><div class="empty">加载中…</div></div>
  </div>
  <script>
  (function () {
    var tabs = document.querySelectorAll('#moduleTabs a');
    var content = document.getElementById('moduleTabContent');
    function loadTab(key) {
      content.innerHTML = '<div class="empty">加载中…</div>';
      fetch('/console/host/' + hostId + '/module-tab?key=' + encodeURIComponent(key), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (r) { return r.text(); }).then(function (html) {
        content.innerHTML = html || '<div class="empty">暂无内容</div>';
      }).catch(function () {
        content.innerHTML = '<div class="empty">加载失败，请稍后重试</div>';
      });
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.classList.remove('active'); });
        tab.classList.add('active');
        loadTab(tab.dataset.key);
      });
    });
    if (tabs.length) loadTab(tabs[0].dataset.key);
  })();
  </script>
  <?php endif; ?>
</div>

<script>
var hostId = <?= (int)$host['id'] ?>;
var msgBox = document.getElementById('actionMsg');
function showMsg(t, ok) {
  msgBox.style.color = ok ? 'var(--success)' : 'var(--danger)';
  msgBox.textContent = t;
}
document.querySelectorAll('.host-action').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var func = btn.dataset.func;
    if (func === 'repassword') { document.getElementById('repasswordBox').hidden = false; return; }
    if (func === 'reinstall') {
      document.getElementById('reinstallBox').hidden = false;
      fetch('/console/host/' + hostId + '/os', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }).then(function (j) {
        var sel = document.getElementById('osSelect');
        sel.innerHTML = '';
        if (j.code === 0 && j.data && j.data.length) {
          j.data.forEach(function (os) {
            var opt = document.createElement('option');
            opt.value = os.id; opt.textContent = os.name;
            sel.appendChild(opt);
          });
        } else { sel.innerHTML = '<option>获取失败</option>'; }
      });
      return;
    }
    if (!confirm('确定执行「' + btn.textContent + '」吗？')) return;
    btn.disabled = true;
    post('/console/host/' + hostId + '/action', { func: func }, function (j) {
      btn.disabled = false;
      if (j.code === 0) {
        showMsg(j.message || '指令已发送', true);
        if (j.data && j.data.url) {
          document.getElementById('vncBox').hidden = false;
          document.getElementById('vncLink').href = j.data.url;
        }
      } else showMsg(j.message || '执行失败', false);
    });
  });
});
document.getElementById('btnDoRepassword').addEventListener('click', function () {
  var p = document.getElementById('newPassword').value;
  if (!p) { alert('请输入新密码'); return; }
  post('/console/host/' + hostId + '/action', { func: 'repassword', password: p }, function (j) {
    showMsg(j.message || (j.code === 0 ? '密码已重置' : '失败'), j.code === 0);
  });
});
document.getElementById('btnDoReinstall').addEventListener('click', function () {
  if (!confirm('重装将清空所有数据，确定继续吗？')) return;
  var osId = document.getElementById('osSelect').value;
  post('/console/host/' + hostId + '/action', { func: 'reinstall', os_id: osId }, function (j) {
    showMsg(j.message || (j.code === 0 ? '重装指令已发送' : '失败'), j.code === 0);
  });
});
document.getElementById('btnSync').addEventListener('click', function () {
  post('/console/host/' + hostId + '/sync', {}, function (j) {
    showMsg(j.message || (j.code === 0 ? '同步成功' : '同步失败'), j.code === 0);
    if (j.code === 0) setTimeout(function () { location.reload(); }, 800);
  });
});
document.getElementById('btnPower').addEventListener('click', function () {
  fetch('/console/host/' + hostId + '/power', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }).then(function (j) {
    showMsg(j.code === 0 ? '电源状态：' + JSON.stringify(j.data) : (j.message || '查询失败'), j.code === 0);
  });
});
</script>
