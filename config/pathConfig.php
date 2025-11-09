<?php

$dir = __DIR__;
while (basename($dir) !== 'public_html') {
    $parent = dirname($dir);
    if ($parent === $dir) {
        die('public_html フォルダが見つかりません（BASE_PATH）');
    }
    $dir = $parent;
}
define('BASE_PATH', $dir); // 例：C:/xampp/htdocs/湘南ちがさき/public_html

// -------------------------------
// URLパス（BASE_URL）定義
// -------------------------------
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// SCRIPT_NAME → 例: /project/public_html/index.php
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

// /public_html より前のパスだけを取り出す
$beforePublicHtml = strstr($scriptPath, '/public_html', true);

define('BASE_URL', $protocol . $host . $beforePublicHtml); // 例: https://example.com/project

