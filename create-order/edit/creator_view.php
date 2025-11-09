<?php
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

    // 公開状態変更処理
    if (isset($_POST['toggle_status'])) {
        $id = $_POST['order_id'];
        $currentStatus = $_POST['current_status'];
        $statuses = ['not_yet', 'creating', 'completed', 'stopped', 'canceled'];
        // 現在のインデックスを取得
        $currentIndex = array_search($currentStatus, $statuses);
        // 次のインデックスを計算（末尾なら先頭に戻る）
        $nextIndex = ($currentIndex + 1) % count($statuses);
        // 次のステータスを取得
        $nextStatus = $statuses[$nextIndex];

        $stmt = $pdo->prepare("UPDATE orders SET progress = ? WHERE id = ?");
        $stmt->execute([$nextStatus, $id]);

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(["status" => "success", "nextStatus" => $nextStatus]);
        exit;
    }

    // 記事削除処理
    if (isset($_POST['delete_order'])) {
        $id = $_POST['order_id'];
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        exit;
    }

    // 外注価格変更処理
    if (isset($_POST['contractPrice'])) {
        $id = $_POST['order_id'] ?? null;
        $contractPrice = $_POST['contractPrice'] ?? null;

        if ($id && $contractPrice) {
            $stmt = $pdo->prepare("UPDATE orders SET contractPrice = ? WHERE id = ?");
            $stmt->execute([$contractPrice, $id]);

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(["status" => "success"]);
        } else {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(["status" => "error", "message" => "値が不正です"]);
        }
        exit;
    }



     // Ajaxリクエストの処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $id     = $_POST['id'] ?? null;
        $field  = $_POST['field'] ?? null;
        $value  = $_POST['value'] ?? null;

        if ($action === 'edit_orders' && $id && $field) {
            $sql = "UPDATE orders SET {$field} = :value WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':value', $value, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(["status" => "success"]);
            exit;
        } else {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(["status" => "error", "message" => "パラメータ不足"]);
            exit;
        }
    }

    // 制作依頼一覧を取得するSQL
    $stmt = $pdo->prepare("SELECT id, date, deadline, creator_name, order_place,order_person, title, mainText, progress, attached_files, creator_sort_order, place_sort_order, man_hour, size, process, paper_amount, flyer_cost, contractPrice, draft, updateNum FROM orders WHERE draft != '1' ORDER BY creator_sort_order DESC");
    $stmt->execute();

    // price_table 読み込み
    $stmtPrice = $pdo->query("SELECT * FROM price_table ORDER BY id ASC");
    $rows = $stmtPrice->fetchAll();
    $priceTable = [];
    foreach ($rows as $r) {
        $priceTable[$r['size_name']][$r['process_name']] = $r['cost'];
    }

    // 記事数を取得
    $stmtColmn = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE progress != 'draft'");
    $stmtColmn-> execute();
    $count = $stmtColmn->fetchColumn();

    // 制作者の一覧を重複なしで取得
    $stmtCreators = $pdo->prepare("SELECT DISTINCT creator_name FROM orders WHERE progress != 'draft' ORDER BY creator_name ASC");
    $stmtCreators->execute();
    $creatorList = $stmtCreators->fetchAll(PDO::FETCH_COLUMN);

    // 依頼担当の一覧を重複なしで取得
    $stmtPlace = $pdo->prepare("SELECT DISTINCT order_place FROM orders WHERE progress != 'draft' ORDER BY order_place ASC");
    $stmtPlace->execute();
    $placeList = $stmtPlace->fetchAll(PDO::FETCH_COLUMN);

    // 取得結果を配列に格納
    $orderList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 外注価格表読み込み
    // id / salesContent / salesPrice を連想配列で取得する
    $stmt = $pdo->query("SELECT id, salesContent, salesPrice FROM contractPrice ORDER BY id ASC");
    $contractPrice = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------
    // 3) 掲載順の上下移動処理
    // --------------------------------------
    if (isset($_POST['moveTopic'])) {
        $id = $_POST['order_id'];
        $direction = $_POST['direction']; // 'up' or 'down'

        // 今の creator_sort_order を取得
        $stmt = $pdo->prepare("SELECT creator_sort_order FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $currentSort = $stmt->fetchColumn();

        if ($currentSort !== false) {
            if ($direction === 'up') {
                // 上に移動:
                // creator_sort_order が現在より小さい中で最も大きい(＝真上にある)ものを見つける
                $stmt = $pdo->prepare("
                    SELECT id, creator_sort_order
                    FROM orders
                    WHERE creator_sort_order < ?
                    ORDER BY creator_sort_order DESC
                    LIMIT 1
                ");
                $stmt->execute([$currentSort]);
                $upperItem = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($upperItem) {
                    // 見つかったバナーと creator_sort_order を入れ替える
                    $stmt = $pdo->prepare("UPDATE orders SET creator_sort_order = ? WHERE id = ?");
                    // 自分を真上の creator_sort_order に
                    $stmt->execute([$upperItem['creator_sort_order'], $id]);
                    // 真上だった方を自分のもともとの creator_sort_order に
                    $stmt->execute([$currentSort, $upperItem['id']]);
                }
            } elseif ($direction === 'down') {
                // 下に移動:
                // creator_sort_order が現在より大きい中で最も小さい(＝真下にある)ものを見つける
                $stmt = $pdo->prepare("
                    SELECT id, creator_sort_order
                    FROM orders
                    WHERE creator_sort_order > ?
                    ORDER BY creator_sort_order ASC
                    LIMIT 1
                ");
                $stmt->execute([$currentSort]);
                $lowerItem = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($lowerItem) {
                    // 見つかったバナーと creator_sort_order を入れ替える
                    $stmt = $pdo->prepare("UPDATE orders SET creator_sort_order = ? WHERE id = ?");
                    // 自分を真下の creator_sort_order に
                    $stmt->execute([$lowerItem['creator_sort_order'], $id]);
                    // 真下だった方を自分のもともとの creator_sort_order に
                    $stmt->execute([$currentSort, $lowerItem['id']]);
                }
            }
        }
        exit;
    }

    // ページネーション設定
    $perPage = 200; // 1ページあたりの表示件数
    $total = count($orderList); // 全件数
    $totalPages = ceil($total / $perPage); // 総ページ数
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1; // 現在のページ
    $currentPage = max(1, min($totalPages, $currentPage)); // ページ範囲を制限

    // 現在のページに対応するデータを切り出す
    $startIndex = ($currentPage - 1) * $perPage;
    $orderList = array_slice($orderList, $startIndex, $perPage);

    // ページ範囲の計算
    $startPage = max(1, $currentPage - 2); // 表示する最初のページ
    $endPage = min($totalPages, $currentPage + 2); // 表示する最後のページ

    if ($endPage - $startPage < 4) {
        $endPage = min($totalPages, $startPage + 4);
        $startPage = max(1, $endPage - 4);
    };

} catch (PDOException $e) {
    echo "データベースエラー: " . $e->getMessage();
    exit;
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>デザイン依頼一覧</title>
    <!-- Tailwind -->
    <script src="../js/Tailwind/tailwind.js"></script>
</head>


<body class="min-h-screen flex flex-col">

    <!-- メインコンテンツ -->
    <div class="flex flex-1 bg-gray-100">
        <!-- サイドバー -->
        <?php include realpath(__DIR__. '/../include_asset/side_bar.php') ?>

        <!-- コンテンツエリア -->
        <main class="flex-1 m-4 min-w-[1200px]">

            <div class="mx-auto px-4 ">
                <div class="fixed bottom-0 left-0 w-full bg-white text-center border-t border-gray-200 py-4 z-[10]">
                    <a href="./create_new_post.php">
                        <button class="bg-red-800 hover:bg-red-700 text-white py-2 px-6 rounded w-full max-w-sm font-bold text-lg">
                            新規登録
                        </button>
                    </a>
                </div>

                <div class="">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th class="bg-gray-600 text-white font-normal py-3 pl-2 w-[60px] max-w-[60px] text-center text-nowrap">
                                    <select id="progressFilter" class="border border-gray-300 rounded py-1 bg-gray-600 text-white">
                                        <option value="">&nbsp;制作状況&nbsp;</option>
                                        <option value="not_yet">&nbsp;未着手&nbsp;</option>
                                        <option value="creating">&nbsp;制作中&nbsp;</option>
                                        <option value="completed">&nbsp;納品済み&nbsp;</option>
                                        <option value="stopped">&nbsp;保留中&nbsp;</option>
                                        <option value="canceled">&nbsp;中止&nbsp;</option>
                                    </select>
                                </th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[70px] max-w-[70px] text-center text-nowrap">
                                    <select id="creatorFilter" class="border border-gray-300 rounded py-1 bg-gray-600 text-white">
                                        <option value="">&nbsp;担当者&nbsp;</option>
                                        <?php foreach ($creatorList as $creator_name): ?>
                                            <option value="<?= htmlspecialchars($creator_name) ?>">
                                                &nbsp;<?= htmlspecialchars($creator_name) ?>&nbsp;
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[30px] max-w-[30px] text-center">工数<span class="text-[13px] block my-[-5px]">(時間)</span></th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[40px] max-w-[120px] text-center">外注価格</th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[200px] max-w-[300px] min-w-[150px] text-center">依頼内容確認・編集</th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[70px] max-w-[70px] text-center text-nowrap">
                                    <select id="placeFilter" class="border border-gray-300 rounded py-1 bg-gray-600 text-white">
                                        <option value="">&nbsp;依頼主&nbsp;</option>
                                        <?php foreach ($placeList as $order_place): ?>
                                            <option value="<?= htmlspecialchars($order_place) ?>">
                                                &nbsp;<?= htmlspecialchars($order_place) ?>&nbsp;
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[70px] max-w-[70px] text-center text-nowrap">
                                    <select id="deadlineSort" class="border border-gray-300 rounded py-1 bg-gray-600 text-white">
                                        <option value="">&nbsp;希望納期&nbsp;</option>
                                        <option value="desc">&nbsp;昇順&nbsp;</option>
                                        <option value="asc">&nbsp;降順&nbsp;</option>
                                    </select>
                                </th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[50px] max-w-[50px] text-center">発注日</th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[60px] text-center">制作原価<br><span class="text-[13px] block my-[-5px]">(工数含む)</span></th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[60px] max-w-[60px] text-center">並び順</th>
                                <th class="bg-gray-600 text-white font-normal py-3 px-1 w-[50px] max-w-[50px] text-center">削除</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderList as $order): ?>
                                <!-- 切り替えボタン -->
                            <tr class="relative border-y-[10px] border-gray-100">
                                <td class="py-7 pl-2 text-center align-middle">
                                    <?php
                                        // 公開状態で色変更
                                        $statusClasses = [
                                            'not_yet'   => 'bg-[#EFBC30]',  // 未作成
                                            'creating'  => 'bg-[#229982]',  // 作成中
                                            'completed' => 'bg-[#166534]', // 完了
                                            'stopped' => 'bg-gray-600', // 保留中
                                            'canceled'  => 'bg-[#991B1B]',   // キャンセル
                                        ];

                                        // 現在のステータス
                                        $currentStatus = $order['progress'];
                                        // ステータスに基づいたクラスを選択
                                        $statusClass = isset($statusClasses[$currentStatus]) ? $statusClasses[$currentStatus] : 'bg-gray-500';
                                    ?>

                                    <!-- 公開状態切り替え。toggleStatusに -->
                                    <button id="statusBtn-<?= $order['id'] ?>" onclick="toggleStatus(<?= $order['id'] ?>, '<?= $order['progress'] ?>')" class="<?= $statusClass ?> text-white py-1 rounded text-center w-[70px] text-nowrap">
                                        <?php
                                        // 現在のステータス
                                        $currentStatus = $order['progress'];

                                        // ステータスに対応するラベルを定義
                                        $statusLabels = [
                                            'not_yet'   => '未着手',
                                            'creating'  => '制作中',
                                            'completed' => '納品済み',
                                            'stopped' => '保留中',
                                            'canceled'  => '中止',
                                        ];
                                        // ステータスが配列にあれば、それに対応する表示名を返す
                                        $statusLabel = isset($statusLabels[$currentStatus]) ? $statusLabels[$currentStatus] : $currentStatus;
                                        echo htmlspecialchars($statusLabel);
                                        ?>
                                    </button>
                                </td>

                                <!-- 担当者名 -->
                                <td
                                    contenteditable="true"
                                    class="editable py-3 px-2 text-center"
                                    data-type="orders"
                                    data-id= "<?= $order['id'] ?>"
                                    data-field="creator_name"
                                >
                                    <div class="border border-[#dddddd] rounded-[5px] py-3">
                                        <?= htmlspecialchars($order['creator_name']) ?>
                                    </div>
                                </td>

                                <!-- 想定工数 -->
                                <td
                                    contenteditable="true"
                                    class="editable py-3 text-center"
                                    data-type="orders"
                                    data-id= "<?= $order['id'] ?>"
                                    data-field="man_hour"
                                >
                                    <div class="border border-[#dddddd] rounded-[5px] py-3">
                                        <?= htmlspecialchars($order['man_hour']) ?>
                                    </div>
                                </td>

                                <!-- 外注価格 -->
                                <td class="py-3 pl-2 text-center">
                                    <div class="relative inline-block w-[120px] ml-[-20px] max-w-[120px]">
                                        <!-- 選択ボタン -->
                                        <button
                                            id="contractPriceBtn-<?= $order['id'] ?>"
                                            class="w-full h-[50px] border border-gray-300 rounded px-2 text-left break-words text-[13px]"
                                        >
                                            <?php
                                            // 選択中の価格名を表示
                                            $selectedPriceName = '';
                                            foreach($contractPrice as $price){
                                                if($order['contractPrice'] == $price['id']){
                                                    $selectedPriceName = $price['salesContent'];
                                                    break;
                                                }
                                            }
                                            echo htmlspecialchars($selectedPriceName ?: '選択');
                                            ?>
                                        </button>

                                        <!-- ドロップダウンリスト -->
                                        <ul
                                            id="contractPriceList-<?= $order['id'] ?>"
                                            class="absolute hidden w-[200px] max-h-[300px] overflow-auto border border-gray-300 bg-white z-10 rounded mt-1 text-[13px]"
                                        >
                                            <?php foreach($contractPrice as $price): ?>
                                                <li
                                                    class="px-2 py-3 hover:bg-gray-200 break-words cursor-pointer border-b border-[#ebebeb] whitespace-nowrap text-left"
                                                    data-value="<?= $price['id'] ?>"
                                                >
                                                    <?= htmlspecialchars($price['salesContent']) ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </td>

                                <script>
                                     // すべての select に対して処理を設定
                                    document.querySelectorAll('select[name="contractPrice"]').forEach(selectEl => {
                                        const tooltipEl = selectEl.parentElement.querySelector('.tooltip');
                                        // 初期ツールチップテキスト
                                        tooltipEl.textContent = selectEl.options[selectEl.selectedIndex].textContent.trim();
                                        // 選択変更時にツールチップ更新
                                        selectEl.addEventListener('change', () => {
                                        tooltipEl.textContent = selectEl.options[selectEl.selectedIndex].textContent.trim();
                                        });
                                    });
                                </script>

                                <!-- タイトル/カテゴリ -->
                                <?php
                                    if (!is_array($order['size'])) {
                                            $size = explode(',', $order['size']); // 文字列 → 配列
                                            $process = explode(',', $order['process']); // 文字列 → 配列
                                            $paper_amount = explode(',', $order['paper_amount']); // 文字列 → 配列
                                        }
                                    $process_count = count($size);
                                    $flyer_cost = $order['flyer_cost'];
                                    if (!is_array($flyer_cost)) {
                                        $flyer_cost = explode(',', rtrim($flyer_cost, ','));
                                    }
                                    $attached_files = $order['attached_files'];
                                ?>

                                <td class="relative py-3 font-[400] max-w-[200px]">
                                    <a href="./edit_order.php?id=<?= $order['id'] ?>#" class="block hover:bg-[#e9e9e9] py-[15px]">
                                        <div class="font-bold border-dashed border-b-[1px] border-[#ececec] truncate"> <?= htmlspecialchars($order['title']) ?> </div>
                                        <div class="truncate text-[14px] pt-[3px]">
                                            <?php for($i=0; $i<$process_count; $i++):?>
                                                <?= $size[$i],$process[$i]?>
                                                <?php if($paper_amount[$i] != 0):?>
                                                    <?= "&nbsp",$paper_amount[$i]."枚 " ?>
                                                <?php endif ?>
                                                <?php if (isset($flyer_cost[$i]) && $flyer_cost[$i] !== "" && $flyer_cost[$i] !== "0"): ?>
                                                    <?= "入稿費".$flyer_cost[$i]."円　" ?>
                                                <?php endif ?>
                                            <?php endfor; ?>
                                        </div>
                                    </a>
                                    <?php if($attached_files):?>
                                        <a href="./download_files.php?order_id=<?= $order['id'] ?>"
                                            class="block absolute bottom-[10px] right-[20px] w-[30px] h-[30px] hover:w-[35px] hover:h-[35px]"
                                            title="<?= is_array($attached_files) ? count($attached_files).'個のファイル' : 'ファイル' ?>をダウンロード">
                                            <img src="../images/クリップ.png" alt="添付ファイル" class="">
                                        </a>
                                    <?php endif ?>
                                    
                                    <!-- 新着判定 -->
                                    <div class="update-status"
                                        data-order-id="<?= $order['id'] ?>"
                                        data-server-update-num="<?= $order['updateNum'] ?>">
                                    </div>

                                </td>

                                <!-- 依頼者 -->
                                <td class="py-3 text-center">
                                    <?= htmlspecialchars($order['order_place']) ?><br><?= htmlspecialchars($order['order_person']) ?>
                                </td>

                                <!-- 希望納期 -->
                                <td class="py-3 text-center font-[600]" id="deadline"
                                    <?php
                                        $deadlineDate = new DateTime($order['deadline']);
                                        $today = new DateTime('today');
                                        $diffDays = $today->diff($deadlineDate)->format("%r%a"); // 今日との差分（日数）

                                        if ($diffDays <= 1 && $diffDays >= 0) {
                                            echo 'style="color:#d40d0d;"';
                                        } elseif ($diffDays <= 3 && $diffDays >= 0) {
                                            echo 'style="color:#f3aa0e;"';
                                        } elseif ($diffDays <= -1) {
                                            echo 'style="color:#a3a3a3;"';
                                        } elseif ($diffDays > 3) {
                                            echo 'style="color:#07751f;"';
                                        }
                                    ?>
                                >
                                    <?= htmlspecialchars(date('n/j', strtotime($order['deadline']))) ?>
                                </td>


                                <!-- 発注日 -->
                                <td class="py-3 text-center">
                                    <?= htmlspecialchars(date('n/j', strtotime($order['date']))) ?>
                                </td>

                                <!-- 受注見積もり -->
                                <td class="py-3 text-center">
                                    <?php
                                        // サイズ
                                        $size = $order['size'];
                                        // 加工
                                        $process = $order['process'];
                                        // 枚数
                                        $amount = $order['paper_amount'];
                                        // 入稿価格
                                        $flyer_cost = $order['flyer_cost'];
                                        // 工数
                                        $man_hour = $order['man_hour'] * 1300; // 1300は時給

                                        if (!is_array($size)) {
                                            $size = explode(',', $size); // 文字列 → 配列
                                        }
                                        if (!is_array($process)) {
                                            $process = explode(',', $process);
                                        }
                                        if (!is_array($amount)) {
                                            $amount = explode(',', $amount);
                                        }
                                        if (!is_array($flyer_cost)) {
                                            $flyer_cost = explode(',', rtrim($flyer_cost, ','));
                                        }

                                        $process_cost =0;
                                        $count = count($size);

                                        if($amount !== [""]){
                                            for($i=0;  $i<$count; $i++){
                                                $process_cost += $priceTable[$size[$i]][$process[$i]] * $amount[$i] ;
                                            }
                                        }else{
                                            $process_cost =0;
                                        }

                                        if(!empty($flyer_cost) && is_array($flyer_cost) && $flyer_cost !== [""]){
                                            $total_cost = array_sum($flyer_cost) + $man_hour + $process_cost;
                                        }else{
                                            $total_cost = $process_cost + $man_hour;
                                        }
                                    ?>
                                    <?= htmlspecialchars($total_cost) ?>円
                                </td>

                                <!-- 掲載順の上下 -->
                                <td class="py-3">
                                    <div class="flex space-x-1 justify-center">
                                        <button
                                            class="w-8 h-8 bg-gray-100 border border-gray-300 rounded flex items-center justify-center"
                                            onclick="moveTopic(<?= $order['id'] ?>, 'down')"
                                        >
                                            <!-- 上矢印アイコン -->
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </button>
                                        <button
                                            class="w-8 h-8 bg-gray-100 border border-gray-300 rounded flex items-center justify-center"
                                            onclick="moveTopic(<?= $order['id'] ?>, 'up')"
                                        >
                                            <!-- 下矢印アイコン -->
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3 text-center">
                                    <button onclick="deleteNews(<?= $order['id'] ?>)" class="text-gray-600 border-red-800 hover:bg-red-800 hover:text-white px-2 py-1 rounded text-sm">
                                        <div class="px-1">削除</div>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- ページネーション -->
            <div class="flex justify-center items-center gap-2 my-8 text-sm">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">
                        &laquo; 前へ
                    </a>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="?page=<?= $i ?>"
                    class="px-3 py-1 rounded
                        <?= $i == $currentPage
                            ? 'bg-gray-600 text-white font-bold'
                            : 'bg-gray-100 hover:bg-gray-200' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>" class="px-3 py-1 text-gray-600 rounded hover:bg-gray-300">
                        次へ &raquo;
                    </a>
                <?php endif; ?>
            </div>
            <div class="h-[70px]" ></div>
        </main>
    </div>

    <!-- 新着localstorage生成 -->
    <script>
        const updateAll = JSON.parse(localStorage.getItem("updateNum"));
        const orderIds = [<?php foreach($orderList as $order){ echo $order['id'] . ','; } ?>];
        let updateNum;
        // localstorageが存在しなかったときに生成
        if(!updateAll){
            localStorage.setItem("updateNum", JSON.stringify({ <?php foreach($orderList as $order){echo $order['id'].":0,";}?> }));
        }else {
            // orderIdsに存在するがupdateAllに無いidを追加
            let updated = false;
            orderIds.forEach(id => {
                if (!(id in updateAll)) {
                    updateAll[id] = 0;
                    updated = true;
                }
            });
            if (updated) {
                localStorage.setItem("updateNum", JSON.stringify(updateAll));
            }
            console.log(updateAll);
        }
        let orderId = "";
        let serverUpdateNum = "";
        let localData = "";
        let localUpdateNum = "";
    </script>
    <!-- document新着判定 -->
    <script>
        window.addEventListener('pageshow', () => {
            document.querySelectorAll('.update-status').forEach(el => {
                const orderId = parseInt(el.dataset.orderId);
                const serverUpdateNum = parseInt(el.dataset.serverUpdateNum);
                const localData = JSON.parse(localStorage.getItem("updateNum")) || {};
                const localUpdateNum = localData[orderId] ?? 0;

                // 判定ロジック
                if (localUpdateNum === 0) {
                    el.innerHTML =  '<div class="absolute top-[8px] left-[0px] text-[#e00b0b] text-[11px] opacity-70">'
                                        +'<span class="[text-shadow:_0_0_4px_var(--tw-shadow-color)] shadow-red-500 text-[9px]">●</span>&nbsp;新着'
                                    +'</div>';
                } else if (localUpdateNum > serverUpdateNum) {
                    el.innerHTML =  '<div class="absolute top-[8px] left-[0px] text-[#666] text-[11px] opacity-70"> 既読'
                                    +'</div>'
                } else if (localUpdateNum <= serverUpdateNum){
                    el.innerHTML = '<div class="absolute top-[8px] left-[0px] text-[#e00b0b] text-[11px] opacity-70">'
                                        +'<span class="[text-shadow:_0_0_4px_var(--tw-shadow-color)] shadow-red-500 text-[9px]">●</span>&nbsp;本文に更新がありました。'
                                    '</div>';
                }
            });
        });
    </script>
    
    <script>
        // 公開状態切り替え
        async function toggleStatus(id, currentStatus) {
            try {
                const encodedStatus = encodeURIComponent(currentStatus);
                const res = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `toggle_status=1&order_id=${id}&current_status=${encodedStatus}`
                });
                const data = await res.json();
                if (data.status === 'success') {
                    const nextStatus = data.nextStatus || currentStatus;
                    // ボタンの更新
                    const btn = document.getElementById(`statusBtn-${id}`);
                    if (btn) {
                        // ラベルマップ（PHP側と同じラベル）
                        const statusLabels = {
                            'not_yet': '未着手',
                            'creating': '制作中',
                            'completed': '納品済み',
                            'stopped': '保留中',
                            'canceled': '中止'
                        };
                        // クラスマップ
                        const statusClasses = {
                            'not_yet': 'bg-[#EFBC30]',
                            'creating': 'bg-[#229982]',
                            'completed': 'bg-[#166534]',
                            'stopped': 'bg-gray-600',
                            'canceled': 'bg-[#991B1B]'
                        };

                        // クラス更新
                        btn.className = `${statusClasses[nextStatus] || 'bg-gray-500'} text-white py-1 rounded text-center w-[70px] text-nowrap`;
                        // ラベル更新
                        btn.textContent = statusLabels[nextStatus] || nextStatus;
                        // onclick 更新（次回押したときに currentStatus を正しく送る）
                        btn.setAttribute('onclick', `toggleStatus(${id}, '${nextStatus}')`);
                    }
                } else {
                    console.error('更新に失敗しました', data);
                    alert('状態の更新に失敗しました');
                }
            } catch (err) {
                console.error(err);
                alert('通信エラーが発生しました');
            }
        }

        // 削除ボタン
        function deleteNews(id) {
        if (confirm('この依頼を削除しますか？')) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `delete_order&order_id=${id}`
            }).then(() => location.reload())
            .catch((error) => {
                console.log("記事の削除に失敗しました。");
            });
        }}

        // 3) 掲載順を上下に移動
        function moveTopic(id, direction) {
            // direction = 'up' or 'down'
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `moveTopic=1&order_id=${id}&direction=${direction}`
            })
            .then(() => location.reload())
            .catch((error) => {
                console.log("掲載順の変更に失敗しました。", error);
            });
        }

    </script>
    <!-- 外注価格 -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            <?php foreach($orderList as $order): ?>
                (function() {
                    const btn = document.getElementById('contractPriceBtn-<?= $order['id'] ?>');
                    const list = document.getElementById('contractPriceList-<?= $order['id'] ?>');

                    // ボタンクリックでドロップダウン表示/非表示
                    btn.addEventListener('click', () => {
                        list.classList.toggle('hidden');
                    });

                    // リスト項目クリック時
                    list.querySelectorAll('li').forEach(li => {
                        li.addEventListener('click', () => {
                            const value = li.dataset.value;
                            btn.textContent = li.textContent; // ボタンに反映
                            list.classList.add('hidden');

                            // AjaxでDB更新
                            fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `contractPrice=${encodeURIComponent(value)}&order_id=<?= $order['id'] ?>`
                            })
                            .then(res => res.json())
                            .then(res => {
                                if(res.status !== 'success') alert('更新に失敗しました');
                            })
                            .catch(err => console.error(err));
                        });
                    });

                    // 外側クリックで閉じる
                    document.addEventListener('click', e => {
                        if (!btn.contains(e.target) && !list.contains(e.target)) {
                            list.classList.add('hidden');
                        }
                    });
                })();
            <?php endforeach; ?>
        });
        </script>

    <script>

        // 直接入力部分のスクリプト
        document.querySelectorAll('.editable').forEach(el => {
            // 共通の保存処理
            const save = () => {
                let newText = el.innerText.trim();
                // 半角数字に変換（全角→半角）
                newText = newText.replace(/[０-９]/g, s => String.fromCharCode(s.charCodeAt(0) - 0xFEE0));
                const type  = el.dataset.type;
                const id    = el.dataset.id;
                const field = el.dataset.field;

                if (!newText) return; // 空文字は保存しない

                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=edit_${type}&id=${id}&field=${field}&value=${encodeURIComponent(newText)}`
                })
                .then(res => res.json())
                .then(res => {
                    if (res.status === "success") {
                    } else {
                        console.error("更新失敗:", res);
                        alert("更新に失敗しました");
                    }
                })
                .catch(err => console.error(err));
            };

            // Enterキーで保存
            el.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault(); // 改行を防ぐ
                    save();
                    el.blur(); // 保存したらフォーカスを外す
                }
            });

            // フォーカスが外れた時も保存
            el.addEventListener('blur', save);
        });

    </script>

    <!-- 担当者フィルター -->
    <script>
        const creatorFilter = document.getElementById('creatorFilter');
        creatorFilter.addEventListener('change', function() {
            const selected = this.value; // 選択された担当者名
            const rows = document.querySelectorAll('table tbody tr');

            rows.forEach(row => {
                const creatorNameDiv = row.querySelector('td.editable div');
                if (!creatorNameDiv) return;

                const creatorName = creatorNameDiv.innerText.trim();

                // 選択が空ならすべて表示、そうでなければ一致する行だけ表示
                if (selected === "" || creatorName === selected) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>

    <!-- 依頼者フィルター -->
    <script>
        const placeFilter = document.getElementById('placeFilter');
        placeFilter.addEventListener('change', function(){
            const selected = this.value.trim();
            const rows = document.querySelectorAll('table tbody tr');

            rows.forEach(row =>{
                const placeCell = row.querySelector('td:nth-child(7)');
                const placeName = placeCell.innerHTML.split('<br>')[0].trim();
                console.log(placeName);

                if(selected ==="" || placeName === selected){
                    row.classList.remove("hidden");
                }else{
                    row.classList.add("hidden");
                }
            });
        });
    </script>


    <!-- 状態フィルター -->
    <script>
        const progressFilter = document.getElementById('progressFilter');
        progressFilter.addEventListener('change', function() {
            const selected = this.value; // 選択された状態
            const rows = document.querySelectorAll('table tbody tr');
            const statusMap = {
            'not_yet': '未着手',
            'creating': '制作中',
            'completed': '納品済み',
            'stopped': '保留中',
            'canceled': '中止'
            };
            const selectedText = statusMap[selected] || '';

            rows.forEach(row => {
                const statusButton = row.querySelector('td button');
                const status = statusButton ? statusButton.innerText.trim() : '';

                // 選択が空ならすべて表示、そうでなければ一致する行だけ表示
                if (selected === "" || status === selectedText) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>

    <!-- 希望納期フィルター -->
    <script>
        const deadlineSort = document.getElementById('deadlineSort');
        deadlineSort.addEventListener('change', function() {
            const order = this.value; // asc or desc
            const tbody = document.querySelector('table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                const aText = a.querySelector('#deadline')?.innerText.trim(); // 希望納期セル
                const bText = b.querySelector('#deadline')?.innerText.trim();

                // n/j を Date に変換（例: "10/3" → Date）
                const thisYear = new Date().getFullYear();
                const aDate = new Date(`${thisYear}/${aText}`);
                const bDate = new Date(`${thisYear}/${bText}`);

                if (order === 'asc') {
                    return aDate - bDate;
                } else {
                    return bDate - aDate;
                }
            });
            // 並び替えた行を tbody に再挿入
            rows.forEach(tr => tbody.appendChild(tr));
            if(order === ''){
                window.location.reload();
            }
        });
    </script>

</body>
</html>
