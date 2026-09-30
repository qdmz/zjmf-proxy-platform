<?php $this->extend('layout/app'); ?>
<div style="padding:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <h3 style="margin:0">VNC 控制台</h3>
    <a href="javascript:history.back()" class="btn btn-sm">返回</a>
  </div>
  <div id="vncStatus" class="notice">正在连接…</div>
  <div id="vncScreen" style="width:100%;height:70vh;background:#000;border-radius:8px;overflow:hidden"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@novnc/novnc@1.4.0/core/rfb.js" type="module"></script>
<script type="module">
import RFB from 'https://cdn.jsdelivr.net/npm/@novnc/novnc@1.4.0/core/rfb.js';
const params = new URLSearchParams(location.search);
const wsUrl = params.get('url') ? atob(params.get('url')) : '';
const password = params.get('password') || '';
const statusEl = document.getElementById('vncStatus');
const screenEl = document.getElementById('vncScreen');
if (!wsUrl) {
  statusEl.textContent = '缺少 VNC 连接地址';
  statusEl.className = 'alert alert-error';
} else {
  try {
    const rfb = new RFB(screenEl, wsUrl, { credentials: { password } });
    rfb.addEventListener('connect', () => {
      statusEl.textContent = '已连接';
      statusEl.className = 'alert alert-success';
    });
    rfb.addEventListener('disconnect', (e) => {
      statusEl.textContent = '连接已断开' + (e.detail && e.detail.clean ? '' : '（异常断开，请重试）');
      statusEl.className = 'alert alert-error';
    });
    rfb.addEventListener('credentialsrequired', () => {
      statusEl.textContent = '需要输入 VNC 密码';
      statusEl.className = 'notice';
    });
    rfb.scaleViewport = true;
    rfb.resizeSession = false;
  } catch (e) {
    statusEl.textContent = 'VNC 初始化失败: ' + e.message;
    statusEl.className = 'alert alert-error';
  }
}
</script>
