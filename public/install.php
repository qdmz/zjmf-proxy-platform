<?php
/**
 * 安装向导：浏览器访问 /install.php
 * 安装完成后请删除或重命名此文件！
 */
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('Asia/Shanghai');

define('APP_ROOT', dirname(__DIR__));
define('LOCK_FILE', APP_ROOT . '/storage/install.lock');

if (file_exists(LOCK_FILE)) {
    die('<h1>已安装</h1><p>如需重新安装，请删除 storage/install.lock 文件后重试。</p>');
}

$step = $_GET['step'] ?? 'check';
$errors = [];

function check_env(): array
{
    $items = [];
    $items[] = ['PHP 版本 >= 8.0', version_compare(PHP_VERSION, '8.0', '>='), PHP_VERSION];
    $items[] = ['PDO 扩展', extension_loaded('pdo'), ''];
    $items[] = ['PDO MySQL 驱动', extension_loaded('pdo_mysql'), ''];
    $items[] = ['cURL 扩展', extension_loaded('curl'), ''];
    $items[] = ['OpenSSL 扩展', extension_loaded('openssl'), ''];
    $items[] = ['mbstring 扩展', extension_loaded('mbstring'), ''];
    $items[] = ['config 目录可写', is_writable(APP_ROOT . '/config'), APP_ROOT . '/config'];
    $items[] = ['storage 目录可写', is_writable(APP_ROOT . '/storage'), APP_ROOT . '/storage'];
    $items[] = ['storage/logs 目录可写', is_writable(APP_ROOT . '/storage/logs'), APP_ROOT . '/storage/logs'];
    return $items;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装向导 - 智简魔方代理销售平台</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f4f6fb;color:#1e293b;margin:0}
.box{max-width:640px;margin:60px auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
h1{text-align:center;margin-bottom:24px}
.ok{color:#16a34a}.bad{color:#dc2626}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
td,th{padding:8px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:14px}
input{width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;margin-bottom:12px;box-sizing:border-box}
label{font-size:14px;font-weight:600;display:block;margin-bottom:6px}
.btn{display:inline-block;background:#2563eb;color:#fff;padding:10px 24px;border-radius:8px;border:none;cursor:pointer;font-size:15px}
</style></head>
<body><div class="box">
<h1>智简魔方代理销售平台 · 安装向导</h1>

<?php if ($step === 'check'): ?>
  <h3>第一步：环境检查</h3>
  <table>
    <?php $pass = true; foreach (check_env() as $it): if (!$it[1]) $pass = false; ?>
      <tr><td><?= h($it[0]) ?></td><td class="<?= $it[1] ? 'ok' : 'bad' ?>"><?= $it[1] ? '✓ 通过' : '✗ 不通过' ?></td><td><?= h($it[2]) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php if ($pass): ?>
    <a class="btn" href="?step=config">下一步：填写配置</a>
  <?php else: ?>
    <p class="bad">请先解决以上环境问题再继续。</p>
  <?php endif; ?>

<?php elseif ($step === 'config'): ?>
  <h3>第二步：数据库与管理员配置</h3>
  <form method="post" action="?step=install">
    <label>数据库主机</label><input name="db_host" value="127.0.0.1" required>
    <label>数据库端口</label><input name="db_port" value="3306" required>
    <label>数据库名</label><input name="db_name" value="zjmf_proxy" required>
    <label>数据库用户名</label><input name="db_user" value="root" required>
    <label>数据库密码</label><input name="db_pass" type="password">
    <label>数据加密密钥（至少16位，用于加密存储上游密码）</label><input name="crypto_key" required minlength="16" value="<?= h(bin2hex(random_bytes(16))) ?>">
    <label>站点网址（如 https://shop.example.com）</label><input name="site_url" required value="http://<?= h($_SERVER['HTTP_HOST'] ?? 'localhost') ?>">
    <label>管理员用户名</label><input name="admin_user" required pattern="[a-zA-Z0-9_]{4,20}">
    <label>管理员密码（至少6位）</label><input name="admin_pass" type="password" required minlength="6">
    <button class="btn" type="submit">开始安装</button>
  </form>

<?php elseif ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <h3>正在安装…</h3>
  <?php
  try {
      $db = [
          'host' => trim($_POST['db_host']), 'port' => (int)$_POST['db_port'],
          'name' => trim($_POST['db_name']), 'user' => trim($_POST['db_user']),
          'pass' => $_POST['db_pass'] ?? '', 'charset' => 'utf8mb4',
      ];
      $cryptoKey = trim($_POST['crypto_key']);
      if (strlen($cryptoKey) < 16) throw new \Exception('加密密钥至少16位');
      $siteUrl = rtrim(trim($_POST['site_url']), '/');
      $adminUser = trim($_POST['admin_user']);
      $adminPass = $_POST['admin_pass'] ?? '';
      if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $adminUser) || strlen($adminPass) < 6) {
          throw new \Exception('管理员账号或密码格式不正确');
      }
      // 连接并建库
      $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
      $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
      $pdo->exec("USE `{$db['name']}`");
      // 导入 schema
      $sql = file_get_contents(APP_ROOT . '/database/schema.sql');
      if (!$sql) throw new \Exception('无法读取 database/schema.sql');
      // 简单按分号拆分执行（schema 中无存储过程）
      foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
          $pdo->exec($stmt);
      }
      // 写入配置文件
      $configContent = "<?php\nreturn [\n"
          . "    'db' => " . var_export($db, true) . ",\n"
          . "    'crypto_key' => " . var_export($cryptoKey, true) . ",\n"
          . "    'pagesize' => 15,\n"
          . "    'http' => ['timeout' => 30, 'connect_timeout' => 10],\n"
          . "];\n";
      if (!file_put_contents(APP_ROOT . '/config/config.php', $configContent)) {
          throw new \Exception('配置文件写入失败，请检查 config 目录权限');
      }
      // 更新站点网址
      $pdo->exec("UPDATE `settings` SET `v`=" . $pdo->quote($siteUrl) . " WHERE `k`='site_url'");
      // 创建管理员
      $hash = password_hash($adminPass, PASSWORD_DEFAULT);
      $st = $pdo->prepare("INSERT INTO `users` (`username`,`password`,`role`,`status`,`reg_ip`) VALUES (?,?,?,?,?)");
      $st->execute([$adminUser, $hash, 'admin', 1, $_SERVER['REMOTE_ADDR'] ?? '']);
      file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));
      echo '<p class="ok">✓ 安装成功！</p>';
      echo '<p>管理员账号：' . h($adminUser) . '</p>';
      echo '<p class="bad">请立即删除或重命名 public/install.php 文件！</p>';
      echo '<p><a class="btn" href="/admin/login">进入管理后台</a> <a class="btn" style="background:#64748b" href="/">前台首页</a></p>';
  } catch (\Throwable $e) {
      echo '<p class="bad">✗ 安装失败：' . h($e->getMessage()) . '</p>';
      echo '<p><a class="btn" href="?step=config">返回重新填写</a></p>';
  }
  ?>
<?php endif; ?>
</div></body></html>
