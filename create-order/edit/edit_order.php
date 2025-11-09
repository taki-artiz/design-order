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

    // URLパラメータからid取得
    if (isset($_GET['id'])) {
        $id = $_GET['id'];

        // 該当記事の情報を取得
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $postEdit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$postEdit) {
            exit('該当する投稿がありません');
        }

    } else {
        exit('投稿IDが指定されていません');
    }

} catch (PDOException $e) {
    echo "データベースエラー: " . $e->getMessage();
    exit;
}
?>

<!-- quillが実行される前にmainTextを取得しておく -->
<script>
    const initialContent = <?= json_encode($postEdit['mainText'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    console.log(initialContent);
</script>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>デザイン依頼編集</title>
    <!-- Tailwind -->
    <script src="../js/Tailwind/tailwind.js"></script>
    <!-- Quill -->
    <script src="../js/quill/quill.min.js"></script>
    <link rel="stylesheet" href="../js/quill/quill.snow.css">
    <!-- 画像リサイズプラグイン -->
    <script src="../js/quill/image-resize.min.js"></script>
    <!-- カスタムQUILL -->
    <!-- <script src="../js/quill/customQuill.js"></script> -->
    <!-- カスタムCSS -->
    <link rel="stylesheet" href="../js/quill/customQuill.css">

</head>
<body class="min-h-screen flex flex-col">

    <!-- メインコンテンツ -->
    <div class="flex flex-1">
        <!-- サイドバー -->
            <?php include('../include_asset/side_bar.php') ?>


        <!-- コンテンツエリア -->
        <main class="relative flex-1 bg-gray-100">

            <!-- フォーム -->
            <div class="m-4 rounded overflow-hidden">
                <div class="bg-gray-700 text-white p-3 mb-4">
                <h1 class="font-bold"><?= htmlspecialchars($postEdit['title'], ENT_QUOTES, 'UTF-8') ?>の編集中</h1>
                </div>

                <!-- ▼ onsubmit でバリデーション関数を呼ぶ -->
                <form method="post" action="../post/post.php" enctype="multipart/form-data">
                    <div class="bg-white">
                        <!-- タイトル -->
                        <div class="grid grid-cols-[200px,1fr] border-b">
                            <div class="p-4 bg-gray-100 font-medium border-r flex items-center">
                                <span>タイトル</span>
                                <span class="ml-2 text-xs text-red-500 border border-red-500 px-2 py-0.5 rounded-md">必須</span>
                            </div>
                            <div class="p-4">
                                <input type="text" name="title" placeholder="※100文字以内で入力してください" value="<?= htmlspecialchars($postEdit['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    class="h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm">
                            </div>
                        </div>

                        <!-- 希望納期 -->
                        <div class="grid grid-cols-[200px,1fr] border-b">
                            <div class="p-4 bg-gray-100 font-medium border-r flex items-center">
                                <span>希望納期</span>
                                <span class="ml-2 text-xs text-red-500 border border-red-500 px-2 py-0.5 rounded-md">必須</span>
                            </div>
                            <div class="p-4">
                                <input type="date" name="deadline" value="<?= htmlspecialchars($postEdit['deadline'] ?? '') ?>"
                                    class="h-9 rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm max-w-[200px]">
                            </div>
                        </div>

                        <!-- 制作仕様 -->
                        <div class="grid grid-cols-[200px,1fr] border-b">
                            <div class="p-4 bg-gray-100 font-medium border-r flex items-center">
                                <span>出力仕様</span>
                                <span class="ml-2 text-xs text-red-500 border border-red-500 px-2 py-0.5 rounded-md">必須</span>
                            </div>
                            <div class="p-4 flex flex-col space-y-4">

                                <?php
                                $sizearray = explode(',', $postEdit['size'] ?? '');
                                $processarray = explode(',', $postEdit['process'] ?? '');
                                $amountarray = explode(',', $postEdit['paper_amount'] ?? '');
                                $flyer_cost_array = explode(',', $postEdit['flyer_cost'] ?? '');
                                $sizefor = count($sizearray);


                                for($i=0; $i<$sizefor; $i++ ):?>
                                <div class="process flex space-x-4">
                                    <!-- サイズ指定 -->
                                    <select name="size[]"
                                        class="h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm max-w-[150px]">
                                        <option class="text-[#747474]">サイズを選択</option>
                                        <?php
                                        $sizes = ['名刺', 'A5', 'A4','A3', 'A2', 'A1'];
                                        foreach ($sizes as $size) {
                                            $selected = ($sizearray[$i] ?? '') === $size ? 'selected' : '';
                                            echo "<option value=\"{$size}\" {$selected}>{$size}</option>";
                                        }
                                        ?>
                                    </select>
                                    <!-- 加工指定 -->
                                    <select id="process" name="process[]"
                                        class="h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm max-w-[150px]">
                                        <option class="text-[#747474]">加工を選択</option>
                                        <?php
                                        $prosesses = ['そのまま', 'ラミネート', 'パネル','ラミパネ','データ提出','入稿'];
                                        foreach ($prosesses as $process) {
                                            $selected = ($processarray[$i] ?? '') === $process ? 'selected' : '';
                                            echo "<option value=\"{$process}\" {$selected}>{$process}</option>";
                                        }
                                        ?>
                                    </select>

                                    <!-- 出力枚数 -->
                                    <div class="w-[100px] relative items-center flex ">
                                        <input type="number" name="paper_amount[]" placeholder="枚数を入力" value="<?= htmlspecialchars($amountarray[$i] ?? '') ?>"
                                            class="h-9 rounded-md border border-gray-300 bg-white py-1 text-sm shadow-sm text-center w-full">&nbsp;枚
                                    </div>
                                    <?php
                                        $user = $_COOKIE['create_order'];
                                        if($user === "artiz-creative"):
                                    ?>
                                    <!-- 入稿費用 -->
                                    <div id="flyer_cost" class="w-[100px] relative">
                                        <input type="number" name="flyer_cost[]" placeholder="入稿費を入力" value="<?= htmlspecialchars($flyer_cost_array[$i] ?? '') ?>"
                                            class="h-9 rounded-md border border-gray-300 bg-white py-1 text-sm shadow-sm text-center w-full">
                                    </div>
                                    <?php endif ?>
                                    <?php if($i>0):?>
                                        <button type="button" class="delete-button h-9 text-gray-300 rounded-md px-3 font-bold shadow border border-gray-300 hover:bg-gray-400 hover:text-white transition">
                                            ×
                                        </button>
                                    <?php endif ?>
                                </div>
                                <?php endfor; ?>

                                <button type="button" class="add-process h-9 rounded-md border border-gray-300 bg-white py-1 mt-[10px] text-[#696969] shadow-sm text-center w-[200px]">
                                    +
                                </button>
                            </div>
                        </div>
                        <!-- 担当者名 -->
                        <div class="grid grid-cols-[200px,1fr] border-b ">
                            <div class="p-4 bg-gray-100 font-medium border-r flex items-center">
                                <span>担当者名</span>
                            </div>
                            <div class="flex px-4">
                                <?php
                                    $user = $_COOKIE['create_order'];
                                    $hidden ="hidden";
                                    if($user === "artiz-creative"){
                                        $hidden = "";
                                    }
                                ?>
                                <div class="py-4 pr-4 <?= $hidden?>">
                                    <input type="text" name="order_place" placeholder="担当部署" value="<?= htmlspecialchars($postEdit['order_place'] ?? '') ?>"
                                        class="h-9 w-[200px] rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm">
                                </div>
                                <div class="py-4">
                                    <input type="text" name="order_person" placeholder="担当者名" value="<?= htmlspecialchars($postEdit['order_person'] ?? '') ?>"
                                        class="h-9 w-[200px] rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ファイルアップロード -->
                    <div class="grid grid-cols-[200px,1fr] border-b">
                        <div class="p-4 bg-gray-100 font-medium border-r">ファイルを添付</div>
                        <div class="p-4 bg-white">
                            <!-- ドラッグゾーン -->
                            <div id="drop-zone"
                                class="border-2 border-dashed border-gray-400 rounded-lg h-[80px] flex items-center justify-center text-center text-gray-500 cursor-pointer hover:bg-blue-50 transition-all">
                            ここに複数ファイルをドラッグ＆ドロップ<br>またはクリックで選択
                            </div>

                            <!-- ファイル入力（非表示） -->
                            <input type="file" id="file-input" name="files[]" multiple class="hidden">
                            <!-- 選択ファイルリスト -->
                        <div id="file-list" class="flex flex-wrap w-full mt-4 list-none p-0"></div>
                        <input type="hidden" id="existing-files" name="existing_files" value="">
                        </div>
                    </div>

                    <!-- 記事のIDを取得しておく -->
                    <input type="hidden" name="id" value="<?= htmlspecialchars($postEdit['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <!-- Quillリッチテキストエディター -->
                    <div class="grid grid-cols-[200px,1fr] border-b">
                        <div class="p-4 bg-gray-100 font-medium border-r">
                            <span>本文</span>
                            <span class="ml-2 text-xs text-red-500 border border-red-500 px-2 py-0.5 rounded-md">必須</span>
                        </div>
                        <div class="p-4">
                            <div id="editor-container" class="border border-gray-300 bg-white rounded-md"></div>
                            <textarea name="mainText" id="content" class="" style="visibility: hidden;" ></textarea>
                        </div>
                    </div>

                    <div class="h-[100px]"></div>
                    <input type="hidden" name="place_sort_order" value="<?= htmlspecialchars($postEdit['place_sort_order'] ?? '') ?>">
                    <input type="hidden" name="creator_sort_order" value="<?= htmlspecialchars($postEdit['creator_sort_order'] ?? '') ?>">
                    <input type="number" name="updateNum" value="<?= htmlspecialchars($postEdit['updateNum'] ?? '') ?>" class="hidden">
                    <!-- 送信ボタン -->
                    <div class="fixed w-full bottom-0 left-0 right-0 bg-white border-t border-gray-300 py-3 pr-[30px] flex justify-center z-10">
                        <button type="submit" class="bg-red-700 hover:bg-red-800 text-white px-20 py-2 rounded-md">
                            更新する
                        </button>
                    </div>
                </form>
                <!-- ▲ onsubmit でバリデーション関数を呼ぶここまで -->
            </div>
        </main>
    </div>
</body>

<!-- 既読スクリプト -->
<script>
    const currentId = <?= json_encode($id) ?>;
    let updateAll = JSON.parse(localStorage.getItem("updateNum"));

    // IDが存在する場合に+1
    if (currentId in updateAll) {
        updateAll[currentId] = <?= json_encode($postEdit['updateNum'])?> +1;
        localStorage.setItem("updateNum", JSON.stringify(updateAll));
    }
    
    // 自身で投稿したときに既読になるよう submit で +2
    const formUpdate = document.querySelector('form');
    formUpdate.addEventListener('submit', function(e) {
        updateAll[currentId] = <?= json_encode($postEdit['updateNum'])?> +2;
        localStorage.setItem("updateNum", JSON.stringify(updateAll));
    });
</script>


<!-- quill -->
<script>
    document.addEventListener('DOMContentLoaded', () => {

        // Quill用のプラグイン登録
        const ImageResize = window.ImageResize.default;
        Quill.register('modules/imageResize', ImageResize);

        // カスタムフォーマット登録 (蛍光ペン)
        const Inline = Quill.import('blots/inline');
        class HighlightBlot extends Inline {}
        HighlightBlot.blotName = 'highlight';
        HighlightBlot.tagName = 'mark'; // <mark>タグでリッチテキストとして反映
        HighlightBlot.className = 'ql-highlight';  // インラインクラスを適用
        Quill.register(HighlightBlot);

        const quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    ['bold', 'italic', 'underline',{'color': [] }, { 'background': [] },'highlight'],  // カスタム蛍光ペンボタン
                    [{ 'align': [] }],
                    ['image', 'link']
                ],
                imageResize: {
                    parchment: Quill.import('parchment'),
                    modules: ['Resize', 'DisplaySize', 'Toolbar'],// 画像リサイズ用の機能を追加
                },
                clipboard: {
                    matchVisual: false // HTML貼り付け時のクリーニングを緩和
                }
            },
            formats: ['bold', 'italic', 'underline', 'strike', 'color', 'background',
                     'size', 'align', 'image', 'link', 'highlight'] // 許可するフォーマットを明示的に指定
        });

        // カスタムボタンの作成
        var toolbar = quill.getModule('toolbar');
        toolbar.addHandler('highlight', function() {
            const range = quill.getSelection();
            if (range) {
                quill.formatText(range.index, range.length, 'highlight', true);
            }
        });

        // Quillツールバーのツールチップを追加
        const tooltips = {
            'ql-size': '文字サイズ',
            'ql-bold': '太字',
            'ql-italic': '斜体',
            'ql-underline': '下線',
            'ql-color': '文字色の変更',
            'ql-background': '背景色の変更',
            'ql-highlight': '蛍光ペン',
            'ql-align': '揃える',
            'ql-image': '画像を挿入',
            'ql-link': 'リンクを挿入'
        };

        // 各ボタンにツールチップを追加
        Object.entries(tooltips).forEach(([className, tooltipText]) => {
            const button = document.querySelector(`.${className}`);
            if (button) {
                button.setAttribute('title', tooltipText); // シンプルなツールチップ
                button.classList.add('relative', 'group'); // Tailwindクラスで視覚強化

                // カスタムツールチップ (より目立つデザイン)
                const tooltip = document.createElement('div');
                tooltip.className = "absolute hidden group-hover:block bg-black text-white text-xs rounded p-1 whitespace-nowrap";
                tooltip.style.top = "-30px";
                tooltip.style.left = "50%";
                tooltip.style.transform = "translateX(-50%)";
                tooltip.textContent = tooltipText;

                button.appendChild(tooltip);
            }
        });

        setTimeout(() => {
                quill.clipboard.dangerouslyPasteHTML(initialContent);
            }, 100);

        // テキストエリアの自動リサイズ
        const editorContainer = document.getElementById('editor-container');
        const textarea = document.getElementById('content');

        // 初期最小高さ
        editorContainer.style.minHeight = '200px';

        // 内容に応じて高さ調整
        function adjustEditorHeight() {
            editorContainer.style.height = 'auto';
            editorContainer.style.height = editorContainer.scrollHeight + 'px';
        }

        // 初期表示時（DBから内容を読み込んだ後に反映）
        setTimeout(adjustEditorHeight, 200);

        // 入力のたびに高さを更新
        quill.on('text-change', () => {
            textarea.value = quill.root.innerHTML;
            adjustEditorHeight();
        });

        // ファイル選択時の表示更新（要素が存在する場合のみ）
        const thumbnail = document.getElementById('thumbnail');
        if (thumbnail) {
            thumbnail.addEventListener('change', function(e) {
                const fileName = e.target.files[0] ? e.target.files[0].name : '選択されていません';
                if (e.target.nextElementSibling) {
                    e.target.nextElementSibling.textContent = fileName;
                }
            });
        }

        // DBから取得した本文を初期値として設定
        if (initialContent && initialContent.trim() !== '') {
            setTimeout(() => {
                quill.clipboard.dangerouslyPasteHTML(initialContent);
            }, 100);
        }

        // フォーム送信時に textarea に反映
        const form = document.querySelector('form');
        const textareaContent = document.getElementById('content');
        form.addEventListener('submit', () => {
            textareaContent.value = quill.root.innerHTML;
        });
    });
</script>

<!-- ▼ JavaScript バリデーション追加 -->
    <script>
        function validateForm() {
        const title = document.querySelector('input[name="title"]').value.trim();
        const sizeSelects = document.querySelectorAll('select[name="size[]"]');
        const processSelects = document.querySelectorAll('select[name="process[]"]');
        const paperInputs = document.querySelectorAll('input[name="paper_amount[]"]');
        const quillContent = document.querySelector('.ql-editor').innerHTML.trim();

        // 本文を textarea に反映
        document.getElementById('content').value = quillContent;

        // タイトル
        if (!title) {
            alert('タイトルを入力してください');
            return false;
        }

        // 各 process ブロックを確認
        for (let i = 0; i < sizeSelects.length; i++) {
            if (sizeSelects[i].value === 'サイズを選択') {
                alert(`(${i+1}行目) サイズを選択してください。`);
                return false;
            }
            if (processSelects[i].value === '加工を選択') {
                alert(`(${i+1}行目) 加工を選択してください。`);
                return false;
            }
            if (!paperInputs[i].value.trim()) {
                alert(`(${i+1}行目) 枚数を入力してください。`);
                return false;
            }
        }

        // 本文チェック
        if (quillContent === '' || quillContent === '<p><br></p>') {
            alert('本文を入力してください');
            return false;
        }

        return true; // OK
    }

    </script>
    <!-- ▲ JavaScript バリデーション追加 -->
<!-- ファイル添付 -->
<script>
    const dropZone = document.getElementById("drop-zone");
    const fileInput = document.getElementById("file-input");
    const fileList = document.getElementById("file-list");
    // 添付済みファイル情報をPHPから取得
    const attachedFiles = <?= json_encode(explode(',', $postEdit['attached_files'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    const uploadFilesDir = '../uploads/';

    // 選択されたファイルを保持する配列
    let allFiles = [];

    function updateFileList() {
        fileList.innerHTML = "";
        // 添付済みファイルの表示
        attachedFiles.forEach((file, index) => {
            if (!file) return;
            const li = document.createElement("div");
                li.className = "items-center border-b border-gray-200 py-2 px-2 text-gray-600";
            const span = document.createElement("span");
                span.textContent = file;
            // ダウンロードリンク
            const link = document.createElement("a");
                link.href = uploadFilesDir + file;
                link.textContent = "ダウンロード";
                link.className = "ml-2 text-blue-500 underline";
                link.target = "_blank";
            // 削除ボタン
            const btn = document.createElement("button");
                btn.textContent = "❌";
                btn.className = "ml-2 text-red-500 hover:text-red-700";
                btn.addEventListener("click", () => {
                    attachedFiles.splice(index, 1); // 配列から削除
                    updateFileList();
                });
                li.appendChild(span);
                li.appendChild(link);
                li.appendChild(btn);
                fileList.appendChild(li);
            });
        // 新規選択ファイルの表示
        allFiles.forEach((file, index) => {
            const li = document.createElement("div");
                li.className = "items-center border-b border-gray-200 py-2 px-2 text-gray-600";
            const span = document.createElement("span");
                span.textContent = file.name + " (" + Math.round(file.size / 1024) + " KB)";
            const btn = document.createElement("button");
                btn.textContent = "❌";
                btn.className = "ml-2 text-red-500 hover:text-red-700";
                btn.addEventListener("click", () => {
                    allFiles.splice(index, 1); // 配列から削除
                    syncFileInput();
                    updateFileList();
                });
            li.appendChild(span);
            li.appendChild(btn);
            fileList.appendChild(li);
        });
        // hiddenに既存ファイル名をセット
        document.getElementById('existing-files').value = attachedFiles.join(',');
    }

    function syncFileInput() {
        const dataTransfer = new DataTransfer();
            allFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
    }

    function addFiles(newFiles) {
        allFiles = [...allFiles, ...newFiles]; // 既存のファイルに追加
        syncFileInput();
        updateFileList();
    }

    dropZone.addEventListener("click", () => fileInput.click());

    dropZone.addEventListener("dragover", (e) => {
        e.preventDefault();
        dropZone.classList.add("bg-blue-50", "border-blue-400", "text-blue-600");
    });

    dropZone.addEventListener("dragleave", () => {
        dropZone.classList.remove("bg-blue-50", "border-blue-400", "text-blue-600");
    });

    dropZone.addEventListener("drop", (e) => {
        e.preventDefault();
        dropZone.classList.remove("bg-blue-50", "border-blue-400", "text-blue-600");
        addFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener("change", () => {
        addFiles(fileInput.files);
    });

    // ページ表示時に添付済みファイルを表示
    window.addEventListener("DOMContentLoaded", updateFileList);
</script>

<!-- 加工追加 -->
<script>
    document.querySelector('.add-process').addEventListener('click', function(e) {
        e.preventDefault();

        // 最後の .process ブロックを取得
        const processAreas = document.querySelectorAll('.process');
        const lastProcess = processAreas[processAreas.length - 1];

        // クローンを作成
        const clone = lastProcess.cloneNode(true);

        // inputとselectをリセット
        clone.querySelectorAll('select, input').forEach(el => {
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else {
                el.value = '';
            }
        });

        // 既存の削除ボタンを削除（重複防止）
        const oldDeleteBtn = clone.querySelector('.delete-process, .delete-button');
        if (oldDeleteBtn) oldDeleteBtn.remove();

        // 新しい削除ボタンを作成
        const deleteBtn = document.createElement('button');
        deleteBtn.textContent = '×';
        deleteBtn.type = 'button';
        deleteBtn.className = 'delete-button h-9 text-gray-300 rounded-md px-3 font-bold shadow border border-gray-300 hover:bg-gray-400 hover:text-white transition';

        // 削除ボタンを追加
        clone.appendChild(deleteBtn);

        // 最後の .process の後ろに追加
        lastProcess.insertAdjacentElement('afterend', clone);
    });
</script>
<!-- 項目削除ボタン -->
<script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('delete-button')) {
            e.preventDefault();

            if (confirm('この項目を削除しますか？')) {
                // 親の .process 要素を探して削除
                const processBlock = e.target.closest('.process');
                if (processBlock) {
                    processBlock.remove();
                }
            }
        }
    });
</script>

<!-- エンターでSubmit防止 -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('form').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        }
    });
    });
</script>


</html>
