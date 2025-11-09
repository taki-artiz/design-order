
<?php

// conf接続
require_once realpath(__DIR__ . '/../config/pathConfig.php');
// DB接続
require_once realpath(__DIR__ . '/../../create-order-DBconfig/DBconfig.php');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
    die('DB接続エラー: ' . $e->getMessage());}


    // クッキーでログイン済みならリダイレクト
if (isset($_COOKIE['create_order'])) {
    $username = $_COOKIE['create_order'];

    // DBからユーザー情報を取得してroleを確認
    $stmt = $pdo->prepare("SELECT role FROM login WHERE name = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['role'] === 'admin') {
            header('Location: edit/creator_view.php');
        } else {
            header('Location: edit/orders_view.php');
        }
        exit;
    } else {
        // クッキーに不正なユーザー名が入っていた場合
        // クッキー削除＆ログイン画面へ戻す
        setcookie('create_order', '', time() - 3600, '/'); // クッキー削除
        header('Location: login.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';

  // DBからユーザー情報取得
  $stmt = $pdo->prepare("SELECT * FROM login WHERE name = ?");
  $stmt->execute([$username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user && $password === $user['password']) {
      // 認証成功 → クッキー保存（1週間）
      setcookie('create_order', $username, time() + (24 * 60 * 60), '/');
      setcookie('account_role', $user['role'], time() + (24 * 60 * 60), '/');

      // 管理者かどうかでリダイレクト先を分岐
      if ($user['role'] === 'admin') {
          header('Location: edit/creator_view.php');
      } else {
          header('Location: edit/orders_view.php');
      }
      exit;
  } else {
      $error = 'ユーザー名またはパスワードが間違っています。';
  }
}
?>


<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>アーティズ制作発注アプリ</title>
  <!-- Tailwind -->
    <script src="./js/Tailwind/tailwind.js"></script>

  <style>
    body {
      background-color: #f0f2f5;
    }
    .logo {
      font-family: 'Brush Script MT', cursive;
    }


  </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md flex flex-col items-center">
    <!-- ロゴ部分 -->
    <div class="mb-6 text-center">
      <h1 class="logo text-4xl font-bold text-brown-700" style="color: #5c3d2e;">
        Artiz
      </h1>
      <p class="text-xs text-brown-600" style="color: #5c3d2e;">アーティズ</p>
    </div>

    <!-- フォーム部分 -->
    <div class="bg-white rounded shadow-md p-6 w-full">
        <form method = "post">
            <div class="mb-4">
            <label class="block text-sm text-gray-700 mb-2">ユーザー名</label>
            <input type="text" name="username" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none">
            </div>

            <div class="mb-6">
            <label class="block text-sm text-gray-700 mb-2">パスワード</label>
            <input type="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none">
            </div>

            <div class="relative w-full flex items-center justify-center mt-8">
            <button type="submit"
                class="bg-gray-800 text-white w-[50%] py-2 rounded hover:bg-gray-700 focus:outline-none">
                ログイン
            </button>
            </div>
        </form>
        <?php if ($error): ?>
            <p style="color:red"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </div>
  </div>
</body>