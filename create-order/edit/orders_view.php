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

    // 記事削除処理
    if (isset($_POST['delete_order'])) {
        $id = $_POST['order_id'];
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        exit;
    }
    // クッキー取得
    $order_place_cookie = $_COOKIE['create_order'] ?? null;
   // order_place が cookie と一致するものだけ取得
    $stmt = $pdo->prepare("SELECT id, date, deadline, creator_name, order_place, order_person, title, mainText, progress, attached_files, place_sort_order, place_sort_order, man_hour, size, process, paper_amount, flyer_cost
        FROM orders
        WHERE progress != 'draft'
        AND order_place = :order_place
        ORDER BY place_sort_order DESC");
    $stmt->bindValue(':order_place', $order_place_cookie, PDO::PARAM_STR);
    $stmt->execute();

    // 件数カウントも同じ条件に
    $stmtColmn = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE progress != 'draft' AND order_place = :order_place");
    $stmtColmn->bindValue(':order_place', $order_place_cookie, PDO::PARAM_STR);
    $stmtColmn->execute();
    $count = $stmtColmn->fetchColumn();

    // 取得結果を配列に格納
    $orderList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------
    // 3) 掲載順の上下移動処理
    // --------------------------------------
    if (isset($_POST['moveTopic'])) {
        $id = $_POST['order_id'];
        $direction = $_POST['direction']; // 'up' or 'down'

        // クッキーから order_place を取得
        $order_place_cookie = $_COOKIE['create_order'] ?? null;

        // 現在の place_sort_order を取得（id かつ order_place がクッキーと一致するもの）
        $stmt = $pdo->prepare("
            SELECT place_sort_order
            FROM orders
            WHERE id = ?
            AND order_place = ?
        ");
        $stmt->execute([$id, $order_place_cookie]);
        $currentSort = $stmt->fetchColumn();

        if ($currentSort !== false) {
            if ($direction === 'up') {
                // 上に移動: 同じ order_place 内で place_sort_order が現在より小さい中で最大のもの
                $stmt = $pdo->prepare("
                    SELECT id, place_sort_order
                    FROM orders
                    WHERE order_place = ? AND place_sort_order < ?
                    ORDER BY place_sort_order DESC
                    LIMIT 1
                ");
                $stmt->execute([$order_place_cookie, $currentSort]);
                $upperItem = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($upperItem) {
                    $stmt = $pdo->prepare("UPDATE orders SET place_sort_order = ? WHERE id = ?");
                    $stmt->execute([$upperItem['place_sort_order'], $id]);
                    $stmt->execute([$currentSort, $upperItem['id']]);
                }
            } elseif ($direction === 'down') {
                // 下に移動: 同じ order_place 内で place_sort_order が現在より大きい中で最小のもの
                $stmt = $pdo->prepare("
                    SELECT id, place_sort_order
                    FROM orders
                    WHERE order_place = ? AND place_sort_order > ?
                    ORDER BY place_sort_order ASC
                    LIMIT 1
                ");
                $stmt->execute([$order_place_cookie, $currentSort]);
                $lowerItem = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($lowerItem) {
                    $stmt = $pdo->prepare("UPDATE orders SET place_sort_order = ? WHERE id = ?");
                    $stmt->execute([$lowerItem['place_sort_order'], $id]);
                    $stmt->execute([$currentSort, $lowerItem['id']]);
                }
            }
        }
        exit;
    }
    // ページネーション設定
    $perPage = 50; // 1ページあたりの表示件数
    $total = count($orderList); // 全件数
    $totalPages = ceil($total / $perPage); // 総ページ数
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1; // 現在のページ
    $currentPage = max(1, min($totalPages, $currentPage)); // ページ範囲を制限

    // 現在のページに対応するデータを切り出す
    $startIndex = ($currentPage - 1) * $perPage;
    $orderList = array_slice($orderList, $startIndex, $perPage);

    // 制作者の一覧を重複なしで取得
    $order_place = $_COOKIE['create_order'];
    $stmtCreators = $pdo->prepare("SELECT DISTINCT creator_name FROM orders WHERE progress != 'draft' AND order_place = :order_place ORDER BY creator_name ASC");
    $stmtCreators->bindValue(':order_place', $order_place, PDO::PARAM_STR);
    $stmtCreators->execute();
    $creatorList = $stmtCreators->fetchAll(PDO::FETCH_COLUMN);

    // 依頼担当者の一覧を重複なしで取得
    $stmtOrders = $pdo->prepare("SELECT DISTINCT order_person FROM orders WHERE progress != 'draft' AND order_place = :order_place ORDER BY order_person ASC");
    $stmtOrders->bindValue(':order_place', $order_place, PDO::PARAM_STR);
    $stmtOrders->execute();
    $placeList = $stmtOrders->fetchAll(PDO::FETCH_COLUMN);

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
    <title>デザイン依頼管理</title>
    <!-- Tailwind -->
    <script src="../js/Tailwind/tailwind.js"></script>

</head>
<body class="min-h-screen flex flex-col">

    <!-- メインコンテンツ -->
    <div class="flex flex-1 bg-gray-100">
        <!-- サイドバー -->
        <?php include realpath(__DIR__. '/../include_asset/side_bar.php') ?>

        <!-- コンテンツエリア -->
        <main class="w-full bg-gray-100">

            <div class="w-full h-[25px] bg-gray-600 text-white p-4 flex justify-between items-center">
                <h1 class="text-lg font-bold">制作依頼 登録一覧</h1>
                <span><?php echo $count; ?>件の登録</span>
            </div>

            <div class="max-w-[1400px] mx-auto px-4 py-6">
                <div class="text-center mb-8">
                    <a href="./create_new_post.php">
                        <button class="bg-red-800 hover:bg-red-900 text-white py-3 px-6 rounded w-full max-w-sm font-bold text-lg">
                            新規登録
                        </button>
                    </a>
                </div>

                <div class="overflow-x-auto">
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
                                        <option value="">&nbsp;制作担当&nbsp;</option>
                                        <?php foreach ($creatorList as $creator_name): ?>
                                            <option value="<?= htmlspecialchars($creator_name) ?>">
                                                &nbsp;<?= htmlspecialchars($creator_name) ?>&nbsp;
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th class="bg-gray-600 text-white font-normal py-3 px-4 w-[300px] max-w-[400px] min-w-[150px] text-center">依頼内容確認・編集</th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[50px] max-w-[50px] text-center text-nowrap">
                                    <select id="deadlineSort" class="border border-gray-300 rounded py-1 bg-gray-600 text-white">
                                        <option value="">&nbsp;希望納期&nbsp;</option>
                                        <option value="desc">&nbsp;昇順&nbsp;</option>
                                        <option value="asc">&nbsp;降順&nbsp;</option>
                                    </select>
                                </th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[50px] max-w-[50px] text-center">発注日</th>
                                <th class="bg-gray-600 text-white font-normal py-3 w-[70px] max-w-[70px] text-center text-nowrap">
                                    <select id="placeFilter" class="border border-gray-300 rounded py-1 bg-gray-600 text-white">
                                        <option value="">&nbsp;依頼担当&nbsp;</option>
                                        <?php foreach ($placeList as $order_place): ?>
                                            <option value="<?= htmlspecialchars($order_place) ?>">
                                                &nbsp;<?= htmlspecialchars($order_place) ?>&nbsp;
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th class="bg-gray-600 text-white font-normal py-1 w-[40px] max-w-[40px] text-center">並び順</th>
                                <th class="bg-gray-600 text-white font-normal py-1 px-1 w-[30px] max-w-[30px] text-center">削除</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderList as $order): ?>
                                <!-- 切り替えボタン -->
                            <tr class="relative border-y-[15px] border-gray-100">
                                <td class="py-3 text-center align-middle">
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
                                    <button onclick="toggleStatus(<?= $order['id'] ?>, '<?= $order['progress'] ?>')" class="<?= $statusClass ?> text-white px-2 py-1 rounded text-sm text-center w-[70px] text-nowrap cursor-text">
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
                                <td class="py-3 px-4 text-center">
                                    <div class="rounded-[5px] py-3 ">
                                        <?= htmlspecialchars($order['creator_name']) ?>
                                    </div>
                                </td>

                                <!-- タイトル/本文 -->
                                <?php
                                    $attached_files = $order['attached_files'];
                                ?>

                                <td class="relative py-3 px-4 font-[400] max-w-[200px]">
                                    <a href="./edit_order.php?id=<?= $order['id'] ?>#" class="block hover:bg-[#e9e9e9]">
                                        <div class="font-bold truncate"> <?= htmlspecialchars($order['title']) ?> </div>
                                        <?php
                                            // 画像タグを削除
                                            $text = preg_replace('/<img[^>]*>/i', '', $order['mainText']);

                                            // HTMLタグを削除
                                            $text = strip_tags($text);
                                            // HTMLエンティティをデコード（&nbsp; → 空白）
                                            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                            // 必要なら余計な空白を削除
                                            $text = trim($text);
                                            // 安全に出力
                                            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <div class="truncate line-clamp-1">
                                            <?= $text ?>
                                        </div>
                                    </a>
                                    <?php if($attached_files):?>
                                        <a href="./download_files.php?order_id=<?= $order['id'] ?>"
                                            class="block absolute bottom-[10px] right-[-10px] w-[30px] h-[30px] hover:w-[35px] hover:h-[35px]"
                                            title="<?= is_array($attached_files) ? count($attached_files).'個のファイル' : 'ファイル' ?>をダウンロード">
                                            <img src="../images/クリップ.png" alt="添付ファイル" class="">
                                        </a>
                                    <?php endif ?>
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
                                <td class="py-3 px-4 text-center">
                                    <?= htmlspecialchars(date('n/j', strtotime($order['date']))) ?>
                                </td>

                                <!-- 依頼者 -->
                                <td class="py-3 text-center">
                                    <?= htmlspecialchars($order['order_person']) ?>
                                </td>

                                <!-- 掲載順の上下 -->
                                <td class="py-3 px-4">
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
                                <td class="py-3 px-4 text-center">
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
            <div class="flex justify-center items-center gap-2 mt-8 text-sm">
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
                    <a href="?page=<?= $currentPage + 1 ?>" class="px-3 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">
                        次へ &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        // 削除ボタン
        function deleteNews(id) {
        if (confirm('本当にこの記事を削除しますか？')) {
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
    <script>
        // 直接入力部分のスクリプト
        document.querySelectorAll('.editable').forEach(el => {
            // 共通の保存処理
            const save = () => {
                const newText = el.innerText.trim();
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
                    if (res.status !== "success") {
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
    <!-- 担当デザイナーフィルター -->
    <script>
        const creatorFilter = document.getElementById('creatorFilter');
        creatorFilter.addEventListener('change', function() {
            const selected = this.value; // 選択された担当者名
            const rows = document.querySelectorAll('table tbody tr');

            rows.forEach(row => {
                const creatorNameDiv = row.querySelector('td div');
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
</body>
</html>
