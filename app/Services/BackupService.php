<?php
namespace App\Services;

use App\Core\DB;

/**
 * 网站数据备份还原服务
 * 备份文件存放在 storage/backups/（.htaccess 拒绝直接访问）
 */
class BackupService
{
    /** 允许还原/下载/删除的文件名正则 */
    const FILENAME_PATTERN = '/^[a-zA-Z0-9_\-]+\.(sql|zip)$/';

    public static function backupDir(): string
    {
        $dir = APP_ROOT . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // 拒绝 Web 直接访问
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
        // 防止目录列表
        $idx = $dir . '/index.html';
        if (!is_file($idx)) {
            @file_put_contents($idx, '');
        }
        return $dir;
    }

    /** 检查文件名合法性并返回完整路径，非法返回 null */
    public static function safePath(string $filename): ?string
    {
        $filename = basename(trim($filename));
        if (!preg_match(self::FILENAME_PATTERN, $filename)) {
            return null;
        }
        $path = self::backupDir() . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }
        return $path;
    }

    /** 备份列表 */
    public static function listBackups(): array
    {
        $dir = self::backupDir();
        $list = [];
        foreach (glob($dir . '/*.{sql,zip}', GLOB_BRACE) ?: [] as $f) {
            $name = basename($f);
            $type = substr($name, -4) === '.sql' ? 'db' : 'files';
            $list[] = [
                'name'  => $name,
                'type'  => $type,
                'size'  => filesize($f),
                'mtime' => filemtime($f),
            ];
        }
        usort($list, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $list;
    }

    /** 磁盘剩余空间（字节），失败返回 null */
    public static function freeSpace(): ?int
    {
        $v = @disk_free_space(self::backupDir());
        return $v === false ? null : (int)$v;
    }

    /** 估算数据库大小（字节） */
    public static function estimateDbSize(): int
    {
        try {
            $row = DB::get(
                "SELECT SUM(data_length + index_length) AS s FROM information_schema.tables WHERE table_schema = DATABASE()"
            );
            return (int)($row['s'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** 一键备份数据库，返回文件名 */
    public static function backupDatabase(): string
    {
        $dir = self::backupDir();
        $file = 'db_' . date('Ymd_His') . '.sql';
        $path = $dir . '/' . $file;

        // 磁盘空间检查：预留 2 倍估算大小
        $need = self::estimateDbSize() * 2 + 1048576;
        $free = self::freeSpace();
        if ($free !== null && $free < $need) {
            throw new \RuntimeException('磁盘空间不足，无法备份（需要约 ' . self::fmtSize($need) . '，剩余 ' . self::fmtSize($free) . '）');
        }

        // 优先 mysqldump
        if (self::tryMysqldump($path)) {
            return $file;
        }
        // 降级：PHP 逐表导出
        self::phpDump($path);
        return $file;
    }

    /** 尝试用 mysqldump 导出，成功返回 true */
    protected static function tryMysqldump(string $path): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if (in_array('exec', $disabled, true)) {
            return false;
        }
        $which = trim((string)@shell_exec('which mysqldump 2>/dev/null'));
        if ($which === '' || !is_executable($which)) {
            // 再试常见路径
            foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/local/mysql/bin/mysqldump'] as $p) {
                if (is_executable($p)) { $which = $p; break; }
            }
            if ($which === '') {
                return false;
            }
        }
        $db = [
            'host' => config('db.host', '127.0.0.1'),
            'port' => (int)config('db.port', 3306),
            'name' => config('db.name', ''),
            'user' => config('db.user', ''),
            'pass' => (string)config('db.pass', ''),
        ];
        if ($db['name'] === '' || $db['user'] === '') {
            return false;
        }
        $cmd = escapeshellcmd($which)
            . ' --host=' . escapeshellarg($db['host'])
            . ' --port=' . escapeshellarg((string)$db['port'])
            . ' --user=' . escapeshellarg($db['user'])
            . ' --password=' . escapeshellarg($db['pass'])
            . ' --single-transaction --routines --events --skip-comments'
            . ' ' . escapeshellarg($db['name'])
            . ' > ' . escapeshellarg($path) . ' 2>/dev/null';
        @exec($cmd, $out, $code);
        if ($code === 0 && is_file($path) && filesize($path) > 100) {
            return true;
        }
        @unlink($path);
        return false;
    }

    /** PHP 逐表导出 SQL */
    protected static function phpDump(string $path): void
    {
        $fp = @fopen($path, 'wb');
        if (!$fp) {
            throw new \RuntimeException('无法创建备份文件');
        }
        $w = function (string $s) use ($fp) { fwrite($fp, $s); };
        $w("-- zjmf-proxy-platform 数据库备份\n-- 生成时间：" . date('Y-m-d H:i:s') . "\n-- 生成方式：PHP 逐表导出\n\n");
        $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = DB::all("SHOW FULL TABLES WHERE table_type = 'BASE TABLE'");
        foreach ($tables as $row) {
            $t = array_values($row)[0];
            $create = DB::get("SHOW CREATE TABLE `{$t}`");
            $ddl = $create['Create Table'] ?? '';
            $w("DROP TABLE IF EXISTS `{$t}`;\n{$ddl};\n\n");

            $count = (int)DB::get("SELECT COUNT(*) AS c FROM `{$t}`")['c'];
            $offset = 0;
            $batch = 500;
            while ($offset < $count) {
                $rows = DB::all("SELECT * FROM `{$t}` LIMIT {$offset}, {$batch}");
                if (!$rows) break;
                $cols = array_keys($rows[0]);
                $colList = '`' . implode('`,`', $cols) . '`';
                $vals = [];
                foreach ($rows as $r) {
                    $v = [];
                    foreach ($r as $val) {
                        $v[] = $val === null ? 'NULL' : "'" . addslashes((string)$val) . "'";
                    }
                    $vals[] = '(' . implode(',', $v) . ')';
                }
                $w("INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $vals) . ";\n");
                $offset += $batch;
            }
            $w("\n");
        }
        $w("SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fp);
    }

    /** 一键备份网站文件，返回文件名 */
    public static function backupFiles(): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('服务器未启用 ZipArchive 扩展，无法打包文件');
        }
        $dir = self::backupDir();
        $file = 'files_' . date('Ymd_His') . '.zip';
        $path = $dir . '/' . $file;
        $root = APP_ROOT;

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('无法创建压缩包');
        }
        // 排除项（相对 APP_ROOT）
        $excludes = [
            'config/config.php',
            'storage/logs',
            'storage/backups',
            '.git',
        ];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            $rel = substr($f->getPathname(), strlen($root) + 1);
            $skip = false;
            foreach ($excludes as $ex) {
                if ($rel === $ex || strpos($rel, $ex . '/') === 0) { $skip = true; break; }
            }
            if ($skip) continue;
            if ($f->isDir()) {
                $zip->addEmptyDir($rel);
            } else {
                $zip->addFile($f->getPathname(), $rel);
            }
        }
        $zip->close();
        if (!is_file($path) || filesize($path) === 0) {
            @unlink($path);
            throw new \RuntimeException('文件打包失败');
        }
        return $file;
    }

    /**
     * 还原数据库：先自动备份当前库，再执行 SQL 还原
     * @return string 还原前自动备份的文件名
     */
    public static function restoreDatabase(string $filename): string
    {
        $path = self::safePath($filename);
        if ($path === null || substr($filename, -4) !== '.sql') {
            throw new \RuntimeException('备份文件不存在或格式不正确');
        }
        // 还原前自动备份当前数据
        $autoFile = 'db_auto_before_restore_' . date('Ymd_His') . '.sql';
        $autoPath = self::backupDir() . '/' . $autoFile;
        if (!self::tryMysqldump($autoPath)) {
            self::phpDump($autoPath);
        }

        $sql = @file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new \RuntimeException('备份文件读取失败或为空');
        }
        $stmts = self::splitSql($sql);
        $pdo = DB::pdo();
        try {
            $pdo->beginTransaction();
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($stmts as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                $pdo->exec($stmt);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
        } catch (\Throwable $e) {
            try { $pdo->rollBack(); } catch (\Throwable $e2) {}
            throw new \RuntimeException('还原失败，已回滚：' . $e->getMessage());
        }
        return $autoFile;
    }

    /** 还原网站文件：解压覆盖，config.php 永不被覆盖 */
    public static function restoreFiles(string $filename): void
    {
        $path = self::safePath($filename);
        if ($path === null || substr($filename, -4) !== '.zip') {
            throw new \RuntimeException('备份文件不存在或格式不正确');
        }
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('服务器未启用 ZipArchive 扩展');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('压缩包打开失败');
        }
        $root = APP_ROOT;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) continue;
            // 路径穿越防护
            if (strpos($name, '..') !== false || $name[0] === '/') continue;
            // 敏感配置永不覆盖
            if ($name === 'config/config.php') continue;
            // 不还原备份目录自身
            if ($name === 'storage/backups' || strpos($name, 'storage/backups/') === 0) continue;
            if (substr($name, -1) === '/') {
                @mkdir($root . '/' . $name, 0755, true);
                continue;
            }
            $target = $root . '/' . $name;
            @mkdir(dirname($target), 0755, true);
            $stream = $zip->getStream($name);
            if ($stream) {
                $out = @fopen($target, 'wb');
                if ($out) {
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                }
                fclose($stream);
            }
        }
        $zip->close();
    }

    /** 删除备份 */
    public static function deleteBackup(string $filename): void
    {
        $path = self::safePath($filename);
        if ($path === null) {
            throw new \RuntimeException('备份文件不存在');
        }
        @unlink($path);
    }

    /** 按 SQL 语句切分（处理引号与注释） */
    public static function splitSql(string $sql): array
    {
        // 去掉整行注释
        $lines = explode("\n", $sql);
        $clean = [];
        foreach ($lines as $line) {
            $t = ltrim($line);
            if (strpos($t, '--') === 0 || strpos($t, '#') === 0) continue;
            $clean[] = $line;
        }
        $sql = implode("\n", $clean);

        $stmts = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $buf .= $sql[$i + 1];
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $buf .= $c;
                continue;
            }
            // /* */ 块注释跳过
            if ($c === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                continue;
            }
            if ($c === ';') {
                $stmts[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $stmts[] = $buf;
        }
        return $stmts;
    }

    public static function fmtSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
