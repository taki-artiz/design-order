<?php
// conf接続
require_once realpath(__DIR__ . '/../config/pathConfig.php');
// DB接続
require_once realpath(__DIR__ . '/../../create-order-DBconfig/DBconfig.php');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
    die('DB接続エラー: ' . $e->getMessage());
}

// クッキーが存在しない = 未ログイン → ログインページへ強制リダイレクト
if (!isset($_COOKIE['create_order'])) {
    header('Location: ../login.php');
    exit;
}

// クッキーのユーザー名がDBに存在するかチェック
$stmt = $pdo->prepare("SELECT * FROM login WHERE name = ?");
$stmt->execute([$_COOKIE['create_order']]);
$loginUser = $stmt->fetch();

// 存在しない（無効なクッキー）→ ログインページへ強制リダイレクト
if (!$loginUser) {
    header('Location: ../login.php');
    exit;
}

// クッキーがartiz-creativeじゃなければorders_view.phpにリダイレクト
// creator_view.php だけで実行するアクセス制御
if (basename($_SERVER['PHP_SELF']) === 'creator_view.php') {
    if ($_COOKIE['account_role'] !== "admin") {
        // "artiz-creative" 以外は orders_view.php にリダイレクト
        header('Location: ./orders_view.php');
        exit;
    }
}

?>