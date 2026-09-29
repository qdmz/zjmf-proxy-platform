<?php
namespace App\Core;

/**
 * 图形验证码（GD 优先，无 GD 时降级为文本算式）
 */
class Captcha
{
    /** 生成并存入 session，输出图片 */
    public static function output(): void
    {
        $code = self::makeCode();
        $_SESSION['captcha'] = strtolower($code);
        $_SESSION['captcha_at'] = time();

        if (!extension_loaded('gd')) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $code;
            exit;
        }

        $w = 120; $h = 40;
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 240, 244, 248);
        imagefill($img, 0, 0, $bg);

        // 干扰线
        for ($i = 0; $i < 5; $i++) {
            $c = imagecolorallocate($img, rand(180, 220), rand(180, 220), rand(180, 220));
            imageline($img, rand(0, $w), rand(0, $h), rand(0, $w), rand(0, $h), $c);
        }
        // 噪点
        for ($i = 0; $i < 120; $i++) {
            $c = imagecolorallocate($img, rand(150, 220), rand(150, 220), rand(150, 220));
            imagesetpixel($img, rand(0, $w - 1), rand(0, $h - 1), $c);
        }
        // 字符
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $c = imagecolorallocate($img, rand(20, 120), rand(20, 120), rand(20, 120));
            $x = 12 + $i * 24 + rand(-3, 3);
            $y = rand(8, 16);
            imagechar($img, 5, $x, $y, $code[$i], $c);
        }

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        imagepng($img);
        imagedestroy($img);
        exit;
    }

    /** 校验（不区分大小写，5 分钟有效，一次有效） */
    public static function check(string $input): bool
    {
        $sess = $_SESSION['captcha'] ?? '';
        $at = $_SESSION['captcha_at'] ?? 0;
        unset($_SESSION['captcha'], $_SESSION['captcha_at']);
        if ($sess === '' || time() - $at > 300) {
            return false;
        }
        return hash_equals($sess, strtolower(trim($input)));
    }

    protected static function makeCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
}
