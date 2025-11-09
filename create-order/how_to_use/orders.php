<?php
// キャッシュ取らせない
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

// 認証チェック
require_once '../auth.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">
    <title>使い方</title>
    <!-- tailwind -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen flex flex-col">

    <!-- メインコンテンツ -->
    <div class="flex flex-1 bg-gray-100">
        <!-- サイドバー -->
        <?php include realpath(__DIR__. '/../include_asset/side_bar.php') ?>
        <div class="p-8">
            <p class="text-center text-[1.6rem] font-bold text-[#910505] py-5">▼トップページ</p>
            <img src="./image/画面説明1.jpg" alt="">
            <p class="text-center text-[1.6rem] font-bold text-[#910505] pt-30 pb-4">▼新規登録ページ</p>
            <img src="./image/画面説明2.jpg" alt="">
        </div>
    </div>

</body>
</html>
