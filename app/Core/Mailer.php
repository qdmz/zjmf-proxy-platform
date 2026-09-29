<?php
namespace App\Core;

/**
 * SMTP 邮件发送（无外部依赖）
 * 支持：明文 / STARTTLS / SSL，AUTH LOGIN 认证
 */
class Mailer
{
    protected string $error = '';

    public function getError(): string
    {
        return $this->error;
    }

    /**
     * 发送邮件
     * @param string $to 收件人
     * @param string $subject 主题
     * @param string $html HTML 正文
     * @return bool
     */
    public function send(string $to, string $subject, string $html): bool
    {
        $host = setting('smtp_host', '');
        $port = (int)setting('smtp_port', 587);
        $user = setting('smtp_user', '');
        $pass = Crypto::decrypt(setting('smtp_pass', ''));
        $from = setting('smtp_from', $user);
        $fromName = setting('smtp_from_name', setting('site_name', ''));
        $enc = strtolower(setting('smtp_enc', 'tls')); // tls | ssl | none

        if ($host === '' || $from === '') {
            $this->error = 'SMTP 尚未配置';
            return false;
        }

        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client(
            $remote, $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );
        if (!$fp) {
            $this->error = "连接 SMTP 服务器失败：{$errstr} ({$errno})";
            return false;
        }
        stream_set_timeout($fp, 15);

        $read = function () use ($fp) {
            $data = '';
            while (($line = fgets($fp, 512)) !== false) {
                $data .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') break;
            }
            return $data;
        };
        $cmd = function (string $c, array $expect) use ($fp, $read) {
            fwrite($fp, $c . "\r\n");
            $resp = $read();
            $code = (int)substr($resp, 0, 3);
            return in_array($code, $expect, true) ? $resp : false;
        };

        $greet = $read();
        if ((int)substr($greet, 0, 3) !== 220) {
            $this->error = 'SMTP 握手失败：' . trim($greet);
            fclose($fp);
            return false;
        }

        $hostname = gethostname() ?: 'localhost';
        if ($cmd("EHLO {$hostname}", [250]) === false) {
            $this->error = 'EHLO 被拒绝';
            fclose($fp);
            return false;
        }

        if ($enc === 'tls') {
            if ($cmd('STARTTLS', [220]) === false) {
                $this->error = '服务器不支持 STARTTLS';
                fclose($fp);
                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->error = 'TLS 加密协商失败';
                fclose($fp);
                return false;
            }
            if ($cmd("EHLO {$hostname}", [250]) === false) {
                $this->error = 'TLS 后 EHLO 被拒绝';
                fclose($fp);
                return false;
            }
        }

        if ($user !== '') {
            if ($cmd('AUTH LOGIN', [334]) === false
                || $cmd(base64_encode($user), [334]) === false
                || $cmd(base64_encode($pass), [235]) === false) {
                $this->error = 'SMTP 认证失败，请检查账号密码';
                fclose($fp);
                return false;
            }
        }

        $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encFromName = $fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?=' : '';
        $body = chunk_split(base64_encode($html));
        $headers = "From: " . ($encFromName !== '' ? "{$encFromName} <{$from}>" : $from) . "\r\n"
            . "To: <{$to}>\r\n"
            . "Subject: {$encSubject}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n";

        $ok = $cmd("MAIL FROM:<{$from}>", [250]) !== false
            && $cmd("RCPT TO:<{$to}>", [250, 251]) !== false
            && $cmd('DATA', [354]) !== false;

        if ($ok) {
            fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
            $resp = $read();
            $ok = in_array((int)substr($resp, 0, 3), [250], true);
            if (!$ok) $this->error = '发送被拒绝：' . trim($resp);
        } else {
            $this->error = '收发地址被拒绝';
        }

        $cmd('QUIT', [221]);
        fclose($fp);
        return $ok;
    }

    /** 是否启用了邮件功能 */
    public static function enabled(): bool
    {
        return setting('mail_enabled', '0') === '1' && setting('smtp_host', '') !== '';
    }

    /** 快捷发送（静态） */
    public static function quick(string $to, string $subject, string $html): array
    {
        $m = new self();
        if (!self::enabled()) {
            return ['ok' => false, 'msg' => '邮件功能未启用'];
        }
        $ok = $m->send($to, $subject, $html);
        return ['ok' => $ok, 'msg' => $ok ? '发送成功' : $m->getError()];
    }
}
