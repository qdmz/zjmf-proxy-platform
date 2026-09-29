<?php
namespace App\Core;

class Crypto
{
    /** AES-256-CBC 加密（用于上游密码、主机密码存储） */
    public static function encrypt(string $plain): string
    {
        $key = hash('sha256', Config::get('crypto_key', 'default'), true);
        $iv = random_bytes(16);
        $ct = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ct);
    }

    public static function decrypt(string $cipher): string
    {
        try {
            $key = hash('sha256', Config::get('crypto_key', 'default'), true);
            $raw = base64_decode($cipher);
            if ($raw === false || strlen($raw) < 17) {
                return '';
            }
            $iv = substr($raw, 0, 16);
            $ct = substr($raw, 16);
            $plain = openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $plain === false ? '' : $plain;
        } catch (\Throwable $e) {
            return '';
        }
    }
}
