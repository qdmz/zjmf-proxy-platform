<?php
namespace App\Core;

class Config
{
    private static $data = null;

    public static function load(string $file): void
    {
        self::$data = require $file;
    }

    public static function get(string $key, $default = null)
    {
        if (self::$data === null) {
            return $default;
        }
        $parts = explode('.', $key);
        $v = self::$data;
        foreach ($parts as $p) {
            if (!is_array($v) || !array_key_exists($p, $v)) {
                return $default;
            }
            $v = $v[$p];
        }
        return $v;
    }

    public static function all(): array
    {
        return self::$data ?? [];
    }
}
