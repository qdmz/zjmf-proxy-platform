// 全站通用 JS
function csrfToken() {
  var m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}
function post(url, data, cb) {
  data = data || {};
  data._csrf = csrfToken();
  var body = new URLSearchParams(data);
  fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: body })
    .then(function (r) { return r.json(); })
    .then(function (j) { cb && cb(j); })
    .catch(function () { alert('请求失败，请稍后重试'); });
}
// 可选配置项
document.addEventListener('click', function (e) {
  var el = e.target.closest('.opt-item');
  if (!el) return;
  var group = el.closest('.opt-list');
  group.querySelectorAll('.opt-item').forEach(function (x) { x.classList.remove('selected'); });
  el.classList.add('selected');
  document.dispatchEvent(new Event('quote-change'));
});
