<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\BackupService;

class BackupController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();
        $backups = BackupService::listBackups();
        $free = BackupService::freeSpace();
        return $this->view('admin/backup_index', [
            'title'   => '数据备份',
            'backups' => $backups,
            'free'    => $free,
        ], 'layout_admin');
    }

    /** 一键备份数据库 */
    public function backupDb(): void
    {
        $this->requireAdmin();
        try {
            $file = BackupService::backupDatabase();
            $this->adminLog('备份数据库：' . $file);
            flash('success', '数据库备份成功：' . $file);
        } catch (\Throwable $e) {
            flash('error', '数据库备份失败：' . $e->getMessage());
        }
        $this->redirect('/admin/backup');
    }

    /** 一键备份网站文件 */
    public function backupFiles(): void
    {
        $this->requireAdmin();
        try {
            $file = BackupService::backupFiles();
            $this->adminLog('备份网站文件：' . $file);
            flash('success', '网站文件备份成功：' . $file);
        } catch (\Throwable $e) {
            flash('error', '网站文件备份失败：' . $e->getMessage());
        }
        $this->redirect('/admin/backup');
    }

    /** 下载备份 */
    public function download(string $name): void
    {
        $this->requireAdmin();
        $path = BackupService::safePath(urldecode($name));
        if ($path === null) {
            http_response_code(404);
            die('备份文件不存在');
        }
        $this->adminLog('下载备份：' . basename($path));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** 删除备份 */
    public function delete(): void
    {
        $this->requireAdmin();
        $name = $_POST['name'] ?? '';
        try {
            BackupService::deleteBackup($name);
            $this->adminLog('删除备份：' . $name);
            flash('success', '备份已删除');
        } catch (\Throwable $e) {
            flash('error', '删除失败：' . $e->getMessage());
        }
        $this->redirect('/admin/backup');
    }

    /** 上传 SQL 并还原数据库（先自动备份当前库） */
    public function restoreDb(): void
    {
        $this->requireAdmin();
        try {
            $file = $_FILES['sql_file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('请先选择要上传的 SQL 备份文件');
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'sql') {
                throw new \RuntimeException('只允许上传 .sql 文件');
            }
            if ($file['size'] > 200 * 1048576) {
                throw new \RuntimeException('文件过大（上限 200MB）');
            }
            $dest = BackupService::backupDir() . '/upload_restore_' . date('Ymd_His') . '.sql';
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new \RuntimeException('文件上传失败');
            }
            $autoFile = BackupService::restoreDatabase(basename($dest));
            @unlink($dest);
            $this->adminLog('还原数据库（还原前自动备份：' . $autoFile . '）');
            flash('success', '数据库还原成功。还原前已自动备份当前数据：' . $autoFile);
        } catch (\Throwable $e) {
            flash('error', '数据库还原失败：' . $e->getMessage());
        }
        $this->redirect('/admin/backup');
    }

    /** 上传 zip 并还原网站文件 */
    public function restoreFiles(): void
    {
        $this->requireAdmin();
        try {
            $file = $_FILES['zip_file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('请先选择要上传的 zip 备份文件');
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'zip') {
                throw new \RuntimeException('只允许上传 .zip 文件');
            }
            if ($file['size'] > 500 * 1048576) {
                throw new \RuntimeException('文件过大（上限 500MB）');
            }
            $dest = BackupService::backupDir() . '/upload_files_' . date('Ymd_His') . '.zip';
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new \RuntimeException('文件上传失败');
            }
            BackupService::restoreFiles(basename($dest));
            @unlink($dest);
            $this->adminLog('还原网站文件');
            flash('success', '网站文件还原成功（config.php 未被覆盖）');
        } catch (\Throwable $e) {
            flash('error', '网站文件还原失败：' . $e->getMessage());
        }
        $this->redirect('/admin/backup');
    }
}
