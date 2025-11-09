<?php
// キャッシュ取らせない
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

session_start();
// conf接続
require_once realpath(__DIR__ . '/../../config/pathConfig.php');
// DB接続
require_once realpath(__DIR__ . '/../../../create-order-DBconfig/DBconfig.php');
// 認証チェック
require_once '../auth.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ===== サービス追加 =====
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_account') {
        $mail      = $_POST['mail'] ?? '';
        $username      = $_POST['username'] ?? '';
        $password          = $_POST['password'] ?? '';
        $note         = $_POST['note'] ?? '';

        // インジェクション対策
        if ($id) {
            $stmt = $pdo->prepare("
                INSERT INTO login (mail, name, password, note)
                VALUES (:mail, :name, :password, :note)
            ");
            $stmt->execute([
                ':mail' => $mail,
                ':name' => $username,
                ':password' => $password,
                ':note' => $note
            ]);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ===== ログイン情報取得 =====
    $accounts = $pdo->prepare("SELECT id, mail, name, password, note FROM login ORDER BY id ASC");
    $accounts -> execute();

} catch (PDOException $e) {
    echo "DB接続失敗: " . $e->getMessage() . "<br>";
    echo nl2br($e->getTraceAsString());
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">
    <title>アカウント管理</title>
    <style>
        .editable { cursor: pointer; padding: 2px; }
        .service-item { margin-left: 20px; }
        .delete-btn {
            color: red;
            margin-left: 5px;
            cursor: pointer;
            background-color: #999;
            color: #fff;
            font-size: 0.6rem;
            border-radius: 5px;
        }
        .delete-btn:hover { background-color: red; }
        input {
            border: 1px #999 solid;
            border-radius: 5px;
            padding: 5px 5px;
        }
        .add-btn {
            border: 1px #999 solid;
            border-radius: 5px;
            margin-left: 20px;
            padding: 5px 15px;
            cursor: pointer;
            color: #999;
        }
        .add-btn:hover {
            background-color: #999;
            font-weight: bold;
            color: white;
        }
        p{
            word-break: break-all;
        }
    </style>
    <!-- tailwind -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen flex flex-col">

    <!-- メインコンテンツ -->
    <div class="flex flex-1 bg-gray-100">
        <!-- サイドバー -->
        <?php include realpath(__DIR__. '/../include_asset/side_bar.php') ?>

        <div class="w-full flex flex-col gap-[10px] relative">
                <div class="relative py-[10px] px-[20px] rounded-[6px] w-full flex flex-wrap justify-center ">
                    <div class="w-full bg-gray-700 text-white text-center text-[1.2rem] font-[600] py-4">
                        アカウント管理・発行
                    </div>
                    <!-- サービス追加 -->
                    <form method="post" class="service-add py-[6px] px-[20px] w-full flex relative gap-[15px] mt-[15px] max-w-[1250px]">
                        <input type="text" name="mail" placeholder="　メールアドレス" class="block w-[22%] px-[4]">
                        <input type="text" name="username" placeholder="　ユーザー名" class="block w-[22%] px-[4]">
                        <input type="text" name="password" placeholder="　パスワード" class="block w-[22%] px-[4]">
                        <input type="text" name="note" placeholder="　コメント" class="block w-[22%] px-[4]">
                        <button
                            type="button"
                            onclick="addaccount(this)"
                            class="add-btn whitespace-nowrap"
                        >
                            登録
                        </button>
                    </form>
                    <!-- サービス一覧 -->
                    <div class="w-full flex flex-wrap font-[600] px-[40px] mt-[20px] gap-[15px] max-w-[1250px]">
                        <p class="block w-[22%]">メールアドレス</p>
                        <p class="block w-[22%]">ユーザー名</p>
                        <p class="block w-[22%]">パスワード</p>
                        <p class="block w-[22%]">コメント</p>
                    </div>
                    <ul class="w-full max-w-[1250px] relative px-[20px]">
                        <?php foreach ($accounts as $account): ?>
                            <li data-id="<?= $account['id'] ?>" class="bg-[#fdfdfd] mt-[10px] py-[10px] px-[25px] rounded-[5px] ">
                                <div class="group w-full flex items-center gap-[15px] relative">
                                    <span
                                        contenteditable="true"
                                        oninput="updateField(<?= $account['id'] ?>, 'mail', this.innerText)"
                                        class="block w-[22%] overflow-hidden"
                                    >
                                        <?= htmlspecialchars($account['mail']) ?>
                                    </span>
                                    <span
                                        contenteditable="true"
                                        oninput="updateField(<?= $account['id'] ?>, 'name', this.innerText)"
                                        class="block w-[22%] overflow-hidden"
                                    >
                                        <?= htmlspecialchars($account['name']) ?>
                                    </span>
                                    <span
                                        contenteditable="true"
                                        oninput="updateField(<?= $account['id'] ?>, 'password', this.innerText)"
                                        class="block w-[22%] overflow-hidden"
                                    >
                                        <?= htmlspecialchars($account['password']) ?>
                                    </span>
                                    <span
                                        contenteditable="true"
                                        oninput="updateField(<?= $account['id'] ?>, 'note', this.innerText)"
                                        class="block w-[22%] overflow-hidden"
                                    >
                                        <?= htmlspecialchars($account['note']) ?>
                                    </span>
                                    <button
                                        onclick="deleteItem(<?= $account['id'] ?>)"
                                        class="delete-btn py-[2px] px-[10px] invisible group-hover:visible mx-w-[20px]"
                                    >
                                        削除
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
        </div>
    </div>

    <script>
        // ===== インライン編集 =====
        function updateField(id, field, value) {
            fetch('update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=edit_account&id=${id}&field=${field}&value=${encodeURIComponent(value)}`
            })
            .then(res => res.json())
            .then(res => {
                if (res.status !== "success") {
                    console.error("更新失敗:", res);
                    alert("更新に失敗しました");
                }
            })
            .catch(err => console.error(err));
        }

        // ===== 削除 =====
        function deleteItem(id) {
            if (confirm('削除しますか？')) {
                fetch('update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_account&id=${id}`
                })
                .then(() => location.reload());
            }
        }

        // ===== サービス追加 AJAX =====
        function addaccount(btn) {
            const container = btn.parentElement;
            const mail = container.querySelector('input[name="mail"]').value.trim();
            const username      = container.querySelector('input[name="username"]').value.trim();
            const password      = container.querySelector('input[name="password"]').value.trim();
            const note         = container.querySelector('input[name="note"]').value.trim();

            if (!mail) {
                alert('サービス名を入力してください');
                return;
            }

            const data = new URLSearchParams();
            data.append('action', 'add_account');
            data.append('mail', mail);
            data.append('username', username);
            data.append('password', password);
            data.append('note', note);

            fetch('update.php', { method: 'POST', body: data })
                .then(res => res.json())
                .then(service => {

                    if (service.status === "success") {
                        location.reload();
                    } else {
                        alert("追加に失敗しました");
                    }

                })
                .catch(err => console.error(err));
        }

        // ===== XSS対策用エスケープ =====
        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
