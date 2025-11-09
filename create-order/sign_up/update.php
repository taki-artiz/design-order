<?php
// キャッシュ取らせない
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');

header('Content-Type: application/json; charset=utf-8'); // JSONレスポンス宣言

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

    $action = $_POST['action'] ?? '';
    $id     = $_POST['id'] ?? '';

    // ===== 編集（インライン編集） =====
    if ($action == 'edit_account') {
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';

        $stmt = $pdo->prepare("UPDATE login SET {$field} = :value WHERE id = :id");
        $stmt->execute([':value' => $value, ':id' => $id]);

        echo json_encode([
            'status' => 'success',
            'action' => 'edit',
            'id'     => $id,
            'field'  => $field,
            'value'  => $value
        ]);
        exit;
    }

    // ===== 削除 =====
    if ($action ==  'delete_account') {
        $stmt = $pdo->prepare("DELETE FROM login WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode([
            'status' => 'success',
            'action' => 'delete',
            'id'     => $id
        ]);
        exit;
    }

    // ===== アカウント追加 =====
    if ($action === 'add_account') {
        $mail     = $_POST['mail'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $note     = $_POST['note'] ?? '';

        // バリデーション
        if (!$mail || !$username || !$password) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO login (mail, name, password, note)
            VALUES (:mail, :name, :password, :note)
        ");
        $stmt->execute([
            ':mail'     => $mail,
            ':name'     => $username,
            ':password' => $password,
            ':note'     => $note
        ]);

        $id = $pdo->lastInsertId();

        echo json_encode([
            'status'   => 'success',
            'action'   => 'add_account',
            'id'       => $id,
            'mail'     => $mail,
            'name'     => $username,
            'password' => $password,
            'note'     => $note
        ]);
        exit;
    }

    // ===== 不明なアクション =====
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
