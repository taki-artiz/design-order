<?php
session_start();
// conf接続
require_once realpath(__DIR__ . '/../../config/pathConfig.php');
// DB接続
require_once realpath(__DIR__ . '/../../../create-order-DBconfig/DBconfig.php');
// 認証チェック
require_once '../auth.php';

//--------------------------------------
// PDO接続
//--------------------------------------
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('DB接続エラー: ' . $e->getMessage());
}

//--------------------------------------
// 更新処理（フォーム送信時）
//--------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // price_table 更新
    if (isset($_POST['priceTable'])) {
        foreach ($_POST['priceTable'] as $size => $processes) {
            foreach ($processes as $process => $cost) {
                $stmt = $pdo->prepare("
                    UPDATE price_table
                    SET cost = :cost
                    WHERE size_name = :size_name AND process_name = :process_name
                ");
                $stmt->execute([
                    ':cost' => (float)$cost,
                    ':size_name' => $size,
                    ':process_name' => $process
                ]);
            }
        }
    }

    // sales_table 更新
    if (isset($_POST['salseTable'])) {
        foreach ($_POST['salseTable'] as $size => $processes) {
            foreach ($processes as $process => $cost) {
                $stmt = $pdo->prepare("
                    UPDATE sales_table
                    SET cost = :cost
                    WHERE size_name = :size_name AND process_name = :process_name
                ");
                $stmt->execute([
                    ':cost' => (float)$cost,
                    ':size_name' => $size,
                    ':process_name' => $process
                ]);
            }
        }
    }
}

//--------------------------------------
// price_table 読み込み
//--------------------------------------
$stmt = $pdo->query("SELECT * FROM price_table ORDER BY id ASC");
$rows = $stmt->fetchAll();
$priceTable = [];
foreach ($rows as $r) {
    $priceTable[$r['size_name']][$r['process_name']] = $r['cost'];
}

//--------------------------------------
// sales_table 読み込み
//--------------------------------------
$stmt = $pdo->query("SELECT * FROM sales_table ORDER BY id ASC");
$rows = $stmt->fetchAll();
$salseTable = [];
foreach ($rows as $r) {
    $salseTable[$r['size_name']][$r['process_name']] = $r['cost'];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>原価・外注価格管理</title>
    <script src="../js/Tailwind/tailwind.js"></script>
</head>

<body class="min-h-screen flex bg-gray-100">

    <?php include realpath(__DIR__. '/../include_asset/side_bar.php') ?>

    <div class="flex flex-wrap justify-center max-w-[95%] mx-auto p-8 grid-cols-1 md:grid-cols-2 gap-8">

        <!-- 原価テーブル -->
        <div class="bg-white shadow p-6 max-w-[670px] min-w-[670px]">
            <h2 class="text-xl font-bold mb-4 bg-[#0b0d5c] text-white px-4 py-2 rounded">制作原価表</h2>

            <form method="post" class="space-y-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full border rounded-sm overflow-hidden">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="border border-gray-200 px-4 py-2 text-sm font-semibold whitespace-nowrap">サイズ</th>
                                <?php foreach (array_keys(reset($priceTable)) as $header): ?>
                                    <th class="border border-gray-200 px-4 py-2 text-sm font-semibold"><?= htmlspecialchars($header) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($priceTable as $size => $processes): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 px-3 py-6 text-sm font-medium bg-gray-100 text-gray-700 text-center whitespace-nowrap"><?= htmlspecialchars($size) ?></td>
                                    <?php foreach ($processes as $process => $cost): ?>
                                        <td class="border border-gray-200 px-2 py-2">
                                            <input
                                                type="text"
                                                name="priceTable[<?= htmlspecialchars($size) ?>][<?= htmlspecialchars($process) ?>]"
                                                value="<?= htmlspecialchars($cost) ?>"
                                                class="w-full border border-gray-200 rounded-md px-2 py-1 text-sm text-right focus:ring-1 focus:ring-blue-400 focus:border-blue-400 outline-none"
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center">
                    <button type="submit" class="bg-gray-700 hover:bg-blue-700 text-white font-semibold px-10 py-2 rounded-lg shadow-sm">
                        保存
                    </button>
                </div>
            </form>
        </div>

        <!-- 販売テーブル -->
        <div class="bg-white shadow p-6 max-w-[670px] min-w-[670px]">
            <h2 class="text-xl font-bold mb-4 bg-[#6b0a5e] text-white px-4 py-2 rounded">外注価格表</h2>

            <form method="post" class="space-y-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full border rounded-sm overflow-hidden">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="border border-gray-200 px-4 py-2 text-sm font-semibold whitespace-nowrap">サイズ</th>
                                <?php foreach (array_keys(reset($salseTable)) as $header): ?>
                                    <th class="border border-gray-200 px-4 py-2 text-sm font-semibold"><?= htmlspecialchars($header) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($salseTable as $size => $processes): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 px-3 py-6 text-sm font-medium bg-gray-100 text-gray-700 text-center whitespace-nowrap"><?= htmlspecialchars($size) ?></td>
                                    <?php foreach ($processes as $process => $cost): ?>
                                        <td class="border border-gray-200 px-2 py-2">
                                            <input
                                                type="text"
                                                name="salseTable[<?= htmlspecialchars($size) ?>][<?= htmlspecialchars($process) ?>]"
                                                value="<?= htmlspecialchars($cost) ?>"
                                                class="w-full border border-gray-200 rounded-md px-2 py-1 text-sm text-right focus:ring-1 focus:ring-pink-400 focus:border-pink-400 outline-none"
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center">
                    <button type="submit" class="bg-gray-700 hover:bg-pink-700 text-white font-semibold px-10 py-2 rounded-lg shadow-sm">
                        保存
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
