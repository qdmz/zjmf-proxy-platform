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
let wsUrl = params.get('url') ? atob(params.get('url')) : '';
const password = params.get('password') || '';
const hostToken = params.get('host_token') || '';
const statusEl = document.getElementById('vncStatus');
const screenEl = document.getElementById('vncScreen');
// 上游把真正的 token 放在 host_token 参数里，拼到 WebSocket 地址上
if (hostToken && /[?&]token=(&|$)/.test(wsUrl)) {
  wsUrl = wsUrl.replace(/([?&]token=)(&|$)/, '$1' + encodeURIComponent(hostToken) + '$2');
}
if (!wsUrl) {
  statusEl.textContent = '缺少 VNC 连接地址';
  statusEl.className = 'alert alert-error';
} else if (/[?&]token=(&|$)/.test(wsUrl)) {
  // 上游未返回 VNC 令牌：WebSocket 必被拒绝，直接给出明确提示
  statusEl.textContent = '上游 VNC 服务未返回访问令牌（token 为空），无法建立连接。可能原因：云主机未真正运行、上游 VNC 服务异常。请先尝试重启主机后重试，或联系客服。';
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
