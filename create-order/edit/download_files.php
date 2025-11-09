<?php
session_start();
require_once realpath(__DIR__ . '/../../config/pathConfig.php');
require_once realpath(__DIR__ . '/../../../create-order-DBconfig/DBconfig.php');
require_once '../auth.php';

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
        $stmt = $pdo->prepare("SELECT attached_files, title FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['attached_files']) {
            $files = is_array($result['attached_files']) ? $result['attached_files']
                : explode(',', $result['attached_files']);

            // 単一ファイルの場合は直接ダウンロード
            if (count($files) === 1) {
                $file = trim($files[0]);
                $filepath = realpath(__DIR__ . '/../uploads/' . $file);

                if (file_exists($filepath)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                    header('Content-Length: ' . filesize($filepath));
                    readfile($filepath);
                    exit;
                }
            }
            // 複数ファイルの場合はZIPにまとめる
            else {
                $zip = new ZipArchive();
                $title = $result['title'];
                $zipname = $title . '.zip';
                $zippath = sys_get_temp_dir() . '/' . $zipname;

                if ($zip->open($zippath, ZipArchive::CREATE) === TRUE) {
                    foreach ($files as $file) {
                        $file = trim($file);
                        $filepath = realpath(__DIR__ . '/../uploads/' . $file);
                        if (file_exists($filepath)) {
                            $zip->addFile($filepath, basename($file));
                        }
                    }
                    $zip->close();

                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zipname . '"');
                    header('Content-Length: ' . filesize($zippath));
                    readfile($zippath);
                    unlink($zippath); // 一時ファイルを削除
                    exit;
                }
            }
        }
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo "エラーが発生しました: " . $e->getMessage();
    }
}

header('HTTP/1.1 404 Not Found');
echo "ファイルが見つかりませんでした。";