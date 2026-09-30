<?php
namespace App\Core;

class Logger
{
    public static function log(string $message, string $file = 'app.log'): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
        @file_put_contents($dir . '/' . $file, $line, FILE_APPEND);
    }

    public static function upstream(int $providerId, int $hostId, string $action, $request, $response, bool $success): void
    {
        try {
            DB::insert('upstream_logs', [
                'provider_id' => $providerId,
                'host_id' => $hostId,
                'action' => $action,
                'request' => is_string($request) ? mb_substr($request, 0, 20000) : mb_substr(json_encode($request, JSON_UNESCAPED_UNICODE), 0, 20000),
                'response' => is_string($response) ? mb_substr($response, 0, 20000) : mb_substr(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 0, 20000),
                'success' => $success ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            self::log('upstream log failed: ' . $e->getMessage());
        }
    }
}
