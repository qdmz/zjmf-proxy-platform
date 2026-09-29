<?php
// 复制为 config.php 并按实际环境修改（或使用安装向导自动生成）
return [
    // 数据库
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'zjmf_proxy',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // 数据加密密钥：务必修改为 32 位以上随机字符串
    // 用于加密存储上游 API 密码和实例密码，修改后已加密数据将无法解密
    'crypto_key' => 'CHANGE_ME_TO_A_RANDOM_32_CHARS_KEY!',

    // 后台分页条数
    'pagesize' => 15,

    // 上游 HTTP 请求超时（秒）
    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
    ],
];
