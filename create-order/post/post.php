<?php

session_start();
// conf接続
require_once '../../config/pathConfig.php';
// DB接続
require_once realpath(__DIR__ . '/../../../create-order-DBconfig/DBconfig.php');
// 認証チェック
require_once '../auth.php';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // フォームから受け取る
        $id = $_POST['id'] ?? '';
        $date     = date('y.m.d');
        $deadline     = $_POST['deadline']     ?? '';
        $order_place     = $_POST['order_place']     ?? '';
        $order_person     = $_POST['order_person']     ?? '';
        $title    = $_POST['title']    ?? '';
        $mainText = $_POST['mainText'] ?? '';
        $size = isset($_POST['size']) ? implode(',', (array)$_POST['size']) : '';
        $process = isset($_POST['process']) ? implode(',', (array)$_POST['process']) : '';
        $paper_amount = isset($_POST['paper_amount']) ? implode(',', (array)$_POST['paper_amount']) : '';
        $flyer_cost = isset($_POST['flyer_cost']) ? implode(',', (array)$_POST['flyer_cost']) : '';
        $draft    = $_POST['draft']    ?? '';
        $creator_sort_order    = $_POST['creator_sort_order']    ?? '';
        $place_sort_order    = $_POST['place_sort_order']    ?? '';
        $updateNum    = $_POST['updateNum']    ?? '';

        // order_placeが空ならcookieの値を代入
        if (empty($order_place) && isset($_COOKIE['create_order'])) {
            if($_COOKIE['create_order'] === "artiz-creative"){
            $order_place = "アーティズ";
            }else{
            $order_place = $_COOKIE['create_order'];
            }
        }

        if($updateNum >= 0){
            $updateNum += 1;
        }

         // ▼ (A) mainText中のBase64画像をファイル化する
        $uploadDir = '../images/';  // Quill画像も同じディレクトリにする例
        $base64Images = [];

        // 1) まず <img src="data:...base64,..."> を全て取り出す
        //    data:image/(jpeg|png|gif|...) の部分を正規表現でマッチ
        $pattern = '/<img[^>]+src=["\']data:image\/(jpeg|jpg|png|gif);base64,([^"\']+)["\'][^>]*>/i';
        preg_match_all($pattern, $mainText, $matches, PREG_SET_ORDER);

        // 置き換え用配列
        $search  = [];
        $replace = [];

        foreach ($matches as $match) {
            $fullTag       = $match[0];  // <img src="data:image/xxx;base64,....">
            $imgExtension  = $match[1];  // jpeg|jpg|png|gif
            $base64Data    = $match[2];  // 実際のBase64文字列

            // 2) Base64をデコードしてファイルへ書き込む
            $imgData   = base64_decode($base64Data);
            $filename  = uniqid('img_', true) . '.' . $imgExtension;
            $filePath  = $uploadDir . $filename; // 実際の保存先

            file_put_contents($filePath, $imgData);

            // HTMLに出すときはWebパスにする
            $webPath = BASE_URL . '/create-order/images/' . $filename;
            $localTag = '<img src="'.$webPath.'" alt="">';

            // 置換元→置換先
            $search[]  = $fullTag;
            $replace[] = $localTag;

            // あとで news_images テーブルに登録するため覚えておく
            $base64Images[] = [
                'path'    => $filePath,
                'alt'     => '', // 必要なら正規表現で alt="..." を抜く
            ];
        }

        // 4) mainText内のBase64画像を、ローカルファイルパスへまとめて置き換え
        if (!empty($search)) {
            $mainText = str_replace($search, $replace, $mainText);
        }
         // ここまでで、$mainText は「すでにBase64でなくローカルパスになったHTML」


        // ---------- ファイル添付処理 ----------
        $uploadedFiles = [];
        $uploadFilesDir = '../uploads/';
        if (!empty($_FILES['files']['name'][0])) {
            foreach ($_FILES['files']['name'] as $idx => $origName) {
                if ($_FILES['files']['error'][$idx] === UPLOAD_ERR_OK) {
                    $tmpPath = $_FILES['files']['tmp_name'][$idx];
                    $ext = pathinfo($origName, PATHINFO_EXTENSION);
                    $baseName = pathinfo($origName, PATHINFO_FILENAME);
                    $dateStr = date('Ymd');
                    $newName = $baseName . '_' . $dateStr . '.' . $ext;
                    $destPath = $uploadFilesDir . $newName;

                    if (move_uploaded_file($tmpPath, $destPath)) {
                        $uploadedFiles[] = $newName;
                    }
                }
            }
        }
        // ファイル名をカンマ区切りで保存（例: file1_20250924120000.pdf,file2_20250924120001.jpg）
        $attachedFiles = implode(',', $uploadedFiles);

        $existingFiles = $_POST['existing_files'] ?? '';
        // 新規アップロードファイル処理後
        if ($existingFiles && $attachedFiles) {
            $attachedFiles = $existingFiles . ',' . $attachedFiles;
        } elseif ($existingFiles) {
            $attachedFiles = $existingFiles;
        }

        // ---------- DBへ挿入する ----------
        // 現在の最大place_sort_orderを取得
        if(empty($place_sort_order)){
            $stmt = $pdo->query("SELECT IFNULL(MAX(place_sort_order), 0) AS max_sort FROM orders");
            $maxSort = $stmt->fetchColumn();
            $newSort = $maxSort + 1;
            $place_sort_order = $newSort;
        }

        // 現在の最大place_sort_orderを取得
        if(empty($creator_sort_order)){
            $stmt = $pdo->query("SELECT IFNULL(MAX(creator_sort_order), 0) AS max_sort FROM orders");
            $maxSort = $stmt->fetchColumn();
            $newSort = $maxSort + 1;
            $creator_sort_order = $newSort;
        }

        if (empty($id)) {
            $stmt = $pdo->prepare("
                INSERT INTO orders
                (date, deadline, order_place, order_person, title, mainText, size, process, paper_amount, flyer_cost, draft, attached_files, place_sort_order,creator_sort_order, updateNum)
                VALUES
                (:date, :deadline, :order_place, :order_person, :title, :mainText, :size, :process, :paper_amount, :flyer_cost, :draft, :attached_files, :place_sort_order,:creator_sort_order, :updateNum)
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE orders
                SET date          = :date,
                    deadline      = :deadline,
                    order_place   = :order_place,
                    order_person  = :order_person,
                    title         = :title,
                    mainText      = :mainText,
                    size          = :size,
                    process       = :process,
                    paper_amount  = :paper_amount,
                    flyer_cost   = :flyer_cost,
                    draft         = :draft,
                    attached_files= :attached_files,
                    place_sort_order    = :place_sort_order,
                    creator_sort_order    = :creator_sort_order,
                    updateNum    = :updateNum
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        }

        $stmt->bindParam(':date',         $date);
        $stmt->bindParam(':deadline',     $deadline);
        $stmt->bindParam(':order_place',  $order_place);
        $stmt->bindParam(':order_person', $order_person);
        $stmt->bindParam(':title',        $title);
        $stmt->bindParam(':mainText',     $mainText);
        $stmt->bindParam(':size',         $size);
        $stmt->bindParam(':process',      $process);
        $stmt->bindParam(':paper_amount', $paper_amount);
        $stmt->bindParam(':flyer_cost',   $flyer_cost);
        $stmt->bindParam(':draft',        $draft);
        $stmt->bindParam(':attached_files', $attachedFiles);
        $stmt->bindParam(':place_sort_order',   $place_sort_order);
        $stmt->bindParam(':creator_sort_order',   $creator_sort_order);
        $stmt->bindParam(':updateNum',   $updateNum);

        $stmt->execute();

        // リダイレクト
        if($_COOKIE['create_order'] === "artiz-creative"){
        header('Location: ../edit/creator_view.php');
        }else{
            header('Location: ../edit/orders_view.php');
        }
        exit;
    }
} catch (PDOException $e) {
    echo "データベースエラー: " . $e->getMessage();
}
?>

<!-- 自分で投稿したときに更新の知らせが出ないようにする -->
<script>
    if (currentId in updateAll) {
        updateAll[currentId] += 1;
        console.log("更新成功！")
    }
    localStorage.setItem("updateNum", JSON.stringify(updateAll));
</script>