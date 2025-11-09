<?php
session_start();
require_once '../../config/pathConfig.php';
require_once realpath(__DIR__ . '/../../../create-order-DBconfig/DBconfig.php');
require_once '../auth.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 年だけを重複なしで取得
    $stmt = $pdo->query("
        SELECT DISTINCT YEAR(date) AS year
        FROM orders
        ORDER BY year DESC
    ");
    $selectYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 原価テーブル読み込み
    $stmtCost = $pdo->query("SELECT * FROM price_table ORDER BY id ASC");
    $rows = $stmtCost->fetchAll();
    $costTable = [];
    foreach ($rows as $r) {
        $costTable[$r['size_name']][$r['process_name']] = $r['cost'];
    }

    if (isset($_GET['year'])) {
        $year = intval($_GET['year']);

        $stmt = $pdo->prepare("
            SELECT order_place, date, deadline, paper_amount, process
            FROM orders
            WHERE YEAR(date) = :year
        ");
        $stmt->execute(['year' => $year]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [
            'paper_amount' => [],
            'order_times' => [],
            'urgent_count' => [],
            'cost_sum' => [] 
        ];

        foreach ($rows as $r) {
            $place = $r['order_place'];
            $month = (int)date('n', strtotime($r['date']));
            $deadlineDate = new DateTime($r['deadline']);
            $orderDate = new DateTime($r['date']);
            $diffDays = (int)$orderDate->diff($deadlineDate)->format('%a');

            if (!isset($result['paper_amount'][$place])) {
                $result['paper_amount'][$place] = array_fill(1, 12, 0);
                $result['order_times'][$place] = array_fill(1, 12, 0);
                $result['urgent_count'][$place] = array_fill(1, 12, 0);
                $result['cost_sum'][$place] = array_fill(1, 12, 0);
            }

            // 枚数と件数の加算
            $result['paper_amount'][$place][$month] += (int)$r['paper_amount'];
            $result['order_times'][$place][$month] += 1;

            // ★ deadline-date に基づく加点処理
            if ($diffDays <= 1) {
                $result['urgent_count'][$place][$month] += 2;
            } elseif ($diffDays <= 3) {
                $result['urgent_count'][$place][$month] += 1;
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit;
    }


} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>制作集計</title>
    <script src="../js/Tailwind/tailwind.js"></script>
    <script src="../js/chart.js/chart.js"></script>
</head>
<body class="min-h-screen flex bg-gray-100">
    <?php include realpath(__DIR__. '/../include_asset/side_bar.php') ?>
    <div class="px-10 h-[30px] flex flex-wrap gap-x-[50px] w-full min-w-[1400px]">
        <div class="w-full">
            <select id="yearSelect" class="border rounded p-1 mb-6 w-[100px] h-[35px] m-4">
                <?php foreach ($selectYears as $year): ?>
                    <option value="<?= $year ?>"><?= $year ?>年</option>
                <?php endforeach; ?>
            </select>
        </div>
        <canvas id="paper_amount"></canvas>
        <canvas id="order_times"></canvas>
        <canvas id="oisogi"></canvas>
    </div>


    <script>
        const canvas1 = document.getElementById('paper_amount');
        const canvas2 = document.getElementById('order_times');
        const canvas3 = document.getElementById('oisogi');
        let chart1, chart2, chart3;

        async function loadChart(year) {
            const response = await fetch(`?year=${year}`);
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.log('raw response:', text);
                return;
            }

            const labels = ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];
            const paperObj = data.paper_amount || {};
            const orderObj = data.order_times || {};
            const urgentObj = data.urgent_count || {};

            const allPlaces = Array.from(new Set([
                ...Object.keys(paperObj),
                ...Object.keys(orderObj),
                ...Object.keys(urgentObj)
            ]));


            // --- paper_amount 用 datasets（積み上げ棒）-------------------------------------------------------
            const paperDatasets = allPlaces.map((place, idx) => {
                const arr = [];
                for (let m = 1; m <= 12; m++) {
                    const v = paperObj[place] && (paperObj[place][m] !== undefined) ? Number(paperObj[place][m]) : 0;
                    arr.push(v);
                }
                return {
                    label: place,
                    data: arr,
                    backgroundColor: `hsl(${(idx * 10) % 360}, 60%, 50%)`
                };
            });

            // 描画前に既存チャートを破棄
            if (chart1) chart1.destroy();

            // Canvasサイズを固定（必要ならCSSで親要素に幅指定してもOK）
            canvas1.width = 600;
            canvas1.height = 400;

            chart1 = new Chart(canvas1, {
                type: 'bar',
                data: { labels, datasets: paperDatasets },
                options: {
                    responsive: false,
                    indexAxis: 'y',
                    interaction: {
                        mode: 'index',      
                        intersect: false 
                    },
                    plugins: { title: { display: true, text: `${year}年の発注枚数（店舗別）` } },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
                }
            });

            // --- order_times 用 datasets（折れ線）-------------------------------------------------------
            const orderDatasets = allPlaces.map((place, idx) => {
                const arr = [];
                for (let m = 1; m <= 12; m++) {
                    const v = orderObj[place] && (orderObj[place][m] !== undefined) ? Number(orderObj[place][m]) : 0;
                    arr.push(v);
                }
                return {
                    label: place,
                    data: arr,
                    backgroundColor: `hsl(${(idx * 10) % 360}, 60%, 50%)`
                };
            });

            if (chart2) chart2.destroy();
            canvas2.width = 600;
            canvas2.height = 400;

            chart2 = new Chart(canvas2, {
                type: 'bar',
                data: { labels, datasets: orderDatasets },
                options: {
                    responsive: false,
                    indexAxis: 'y',
                    interaction: {
                        mode: 'index',      
                        intersect: false 
                    },
                    plugins: { title: { display: true, text: `${year}年の注文件数（店舗別）` } },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
                }
            });
            
             // --- お急ぎ件数グラフ -------------------------------------------------------
            const urgentDatasets = allPlaces.map((place, idx) => {
                const arr = [];
                for (let m = 1; m <= 12; m++) {
                    const v = urgentObj[place] && urgentObj[place][m] !== undefined ? Number(urgentObj[place][m]) : 0;
                    arr.push(v);
                }
                return {
                    label: place,
                    data: arr,
                    backgroundColor: `hsl(${(idx * 10) % 360}, 70%, 55%)`
                };
            });

            if (chart3) chart3.destroy();
            canvas3.width = 600;
            canvas3.height = 400;

            chart3 = new Chart(canvas3, {
                type: 'bar',
                data: { labels, datasets: urgentDatasets },
                options: {
                    responsive: false,
                    indexAxis: 'y',
                    interaction: {
                        mode: 'index',      
                        intersect: false 
                    },
                    plugins: { title: { display: true, text: `${year}年の「お急ぎ」件数（店舗別）` } },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
                }
            });
        }

        document.getElementById('yearSelect').addEventListener('change', (e) => {
            loadChart(e.target.value);
        });

        const firstYear = document.getElementById('yearSelect').value;
        loadChart(firstYear);
    </script>

</body>
</html>
