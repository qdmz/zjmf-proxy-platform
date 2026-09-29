<?php
namespace App\Core;

class Http
{
    /**
     * 发起 HTTP 请求
     * @param string $method GET/POST/PUT/DELETE
     * @param string $url
     * @param array $data 表单数据（POST/PUT）或 query 参数（GET）
     * @param array $headers 附加请求头
     * @param int $timeout 超时秒
     * @param bool $asJson 是否以 JSON 发送 body
     * @return array ['http_code'=>int,'body'=>string,'error'=>string]
     */
    public static function request(string $method, string $url, array $data = [], array $headers = [], int $timeout = 30, bool $asJson = false): array
    {
        $ch = curl_init();
        $method = strtoupper($method);

        $defaultHeaders = [];
        if ($method === 'GET' && $data) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if ($asJson) {
                $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
                $defaultHeaders[] = 'Content-Type: application/json';
            } else {
                $payload = http_build_query($data);
                $defaultHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        if ($method !== 'GET' && $method !== 'POST') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $body = curl_exec($ch);
        $result = [
            'http_code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => $body === false ? '' : $body,
            'error' => curl_error($ch),
        ];
        curl_close($ch);
        return $result;
    }

    /** 解析 JSON 响应，失败返回 null */
    public static function json(array $result)
    {
        $data = json_decode($result['body'], true);
        return is_array($data) ? $data : null;
    }
}
