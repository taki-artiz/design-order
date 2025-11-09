<?php
// 認証チェック
require_once '../auth.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>新規依頼作成</title>
    <!-- Tailwind -->
    <script src="../js/Tailwind/tailwind.js"></script>
    <!-- Quill -->
    <script src="../js/quill/quill.min.js"></script>
    <link rel="stylesheet" href="../js/quill/quill.snow.css">
    <!-- 画像リサイズプラグイン -->
    <script src="../js/quill/image-resize.min.js"></script>
    <!-- カスタムCSS -->
    <link rel="stylesheet" href="../js/quill/customQuill.css">

        <!-- quillが実行される前にmainTextを取得しておく -->
<script>
    const initialContent = "";
</script>
</head>
<body class="min-h-screen flex flex-col">

    <!-- メインコンテンツ -->
    <div class="flex flex-1">
        <!-- サイドバー -->
        <?php include('../include_asset/side_bar.php') ?>


        <!-- コンテンツエリア -->
        <main class="flex-1 bg-gray-100">

            <!-- フォーム -->
            <div class="m-4 rounded overflow-hidden">
                <div class="bg-gray-700 text-white p-3 mb-4">
                    <h1 class="font-bold">新規登録</h1>
                </div>

                <!-- ▼ onsubmit でバリデーション関数を呼ぶ -->
                <form id="upload-form" method="post" action="../post/post.php" enctype="multipart/form-data" onsubmit="return validateForm();">
                    <div class="bg-white">

                        <!-- タイトル -->
                        <div class="grid grid-cols-[200px,1fr] border-b">
                            <div class="p-4 bg-gray-100 font-medium border-r flex items-center">
                                <span>タイトル</span>
                                <span class="ml-2 text-xs text-red-500 border border-red-500 px-2 py-0.5 rounded-md">必須</span>
                            </div>
                            <div class="p-4">
                                <input type="text" name="title" placeholder="タイトル入力してください"
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
                                <input type="date" name="deadline" value="<?php echo date('Y-m-d'); ?>"
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
                                <div class="process flex space-x-4">
                                    <!-- サイズ指定 -->
                                    <select name="size[]"
                                        class="h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm max-w-[150px]">
                                        <option class="text-[#747474]">サイズを選択</option>
                                        <?php
                                        $sizes = ['名刺', 'A5', 'A4','A3', 'A2', 'A1'];
                                        foreach ($sizes as $size) {
                                            echo "<option value=\"{$size}\">{$size}</option>";
                                        }
                                        ?>
                                    </select>
                                    <!-- 加工指定 -->
                                    <select name="process[]"
                                        class="h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm max-w-[150px]">
                                        <option class="text-[#747474]">加工を選択</option>
                                        <?php
                                        $prosesses = ['そのまま', 'ラミネート', 'パネル','ラミパネ','データ提出','入稿'];
                                        foreach ($prosesses as $process) {
                                            echo "<option value=\"{$process}\">{$process}</option>";
                                        }
                                        ?>
                                    </select>
                                    <!-- 出力枚数 -->
                                    <div class="w-[100px] relative items-center flex ">
                                        <input type="number" name="paper_amount[]" placeholder="枚数を入力"
                                            class="h-9 rounded-md border border-gray-300 bg-white py-1 text-sm shadow-sm text-center w-full">&nbsp;枚
                                    </div>

                                    <?php
                                    $user = $_COOKIE['account_role'];
                                    if($user === "admin"):
                                    ?>
                                    <!-- 入稿費用 -->
                                    <div class="w-[100px] relative">
                                        <input type="number" name="flyer_cost" placeholder="入稿費を入力"
                                            class="h-9 rounded-md border border-gray-300 bg-white py-1 text-sm shadow-sm text-center w-full">
                                    </div>
                                    <?php endif ?>
                                </div>
                                <button type="button" class="add-process h-9 rounded-md border border-gray-300 bg-white py-1 mt-[10px] text-[#696969] shadow-sm text-center w-[200px]">
                                    +
                                </button>
                            </div>
                        </div>
                        <!-- 担当者名 -->
                        <div class="grid grid-cols-[200px,1fr] border-b">
                            <div class="p-4 bg-gray-100 font-medium border-r flex items-center">
                                <span>担当者名</span>
                            </div>
                            <?php
                                $user = $_COOKIE['account_role'];
                                $hidden ="hidden";
                                if($user === "admin"){
                                    $hidden = "";
                                }
                            ?>
                            <div class="py-4 px-4">
                                <input type="text" name="order_place" placeholder="担当部署"
                                    class="mr-4 h-9 w-[200px] rounded-md border border-gray-300 bg-white pl-3 py-1 text-sm shadow-sm <?= $hidden ?>">
                                <input type="text" name="order_person" placeholder="担当者名"
                                    class="h-9 w-[200px] rounded-md border border-gray-300 bg-white px-3 py-1 text-sm shadow-sm">
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

                    <!-- Quillリッチテキストエディター -->
                    <div class="grid grid-cols-[200px,1fr] border-b">
                        <div class="p-4 bg-gray-100 font-medium border-r">
                            <span>本文</span>
                            <span class="ml-2 text-xs text-red-500 border border-red-500 px-2 py-0.5 rounded-md">必須</span>
                        </div>

                        <div class="p-4">
                            <div id="editor-container" class="h-40 border border-gray-300 bg-white rounded-md"></div>
                            <textarea name="mainText" id="content" class=""></textarea>
                        </div>
                    </div>

                    <?php
                        $user = $_COOKIE['account_role'];
                        $draft_hidden ="hidden";
                        if($user !== "admin"){
                            $draft_hidden = "";
                        }
                        ?>

                    <input type="number" name="updateNum" value="" class="hidden">
                    <!-- 送信ボタン -->
                    <div class="flex justify-center mt-8 mb-4">
                        <button type="submit" class="bg-red-700 hover:bg-red-800 text-white px-20 py-2 rounded-md">
                            登録する
                        </button>
                    </div>
                </form>
            </div>

        </main>
    </div>

    <!-- ▼ JavaScript バリデーション追加 -->
    <script>
        function validateForm() {
            const title = document.querySelector('input[name="title"]').value.trim();
            const sizeSelects = document.querySelectorAll('select[name="size[]"]');
            const processSelects = document.querySelectorAll('select[name="process[]"]');
            const paperInputs = document.querySelectorAll('input[name="paper_amount[]"]');
            const quillContent = document.querySelector('.ql-editor').innerHTML.trim();

            // --- ここから画像サイズ保持処理 ---
            const editor = document.querySelector('.ql-editor');
            const images = editor.querySelectorAll('img');
            images.forEach(img => {
                const computedStyle = window.getComputedStyle(img);
                const width = parseFloat(computedStyle.width);
                const height = parseFloat(computedStyle.height);

                if (width && !img.hasAttribute('width')) {
                    img.setAttribute('width', Math.round(width));
                }
                if (height && !img.hasAttribute('height')) {
                    img.setAttribute('height', Math.round(height));
                }
            });

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
    <!-- ファイル添付 -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('upload-form');
            const textarea = document.getElementById('content');

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
                placeholder: '',
                modules: {
                    toolbar: [
                        [{ 'size': ['small', false, 'large', 'huge'] }],
                        ['bold', 'italic', 'underline',{'color': [] }, { 'background': [] },'highlight'],  // カスタム蛍光ペンボタン
                        [{ 'align': [] }],
                        ['image', 'link']
                    ],
                    imageResize: {
                        parchment: Quill.import('parchment'),
                        modules: ['Resize', 'DisplaySize', 'Toolbar'] // 画像リサイズ用の機能を追加
                    }
                }
            });
            quill.root.innerHTML = '<br><br><br><br><br><br><br>';

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

            // テキストエリアの自動リサイズ
            const editorContainer = document.getElementById('editor-container');

            // 初期最小高さ
            editorContainer.style.minHeight = '200px';

            // 内容に応じて高さ調整
            function adjustEditorHeight() {
                editorContainer.style.height = 'auto';
                editorContainer.style.height = editorContainer.scrollHeight + 'px';
            }

            // 初期表示時（DBから内容を読み込んだ後にも反映）
            setTimeout(adjustEditorHeight, 200);

            // 入力のたびに高さを更新
            quill.on('text-change', () => {
                textarea.value = quill.root.innerHTML;
                adjustEditorHeight();
            });


            // ファイル添付の管理
            const dropZone = document.getElementById("drop-zone");
            const fileInput = document.getElementById("file-input");
            const fileList = document.getElementById("file-list");
            let allFiles = [];

            function updateFileList() {
                fileList.innerHTML = "";
                allFiles.forEach((file, index) => {
                    const li = document.createElement("div");
                    li.className = "items-center border-b border-gray-200 py-2 px-2 text-gray-600";

                    const span = document.createElement("span");
                    span.textContent = file.name + " (" + Math.round(file.size / 1024) + " KB)";

                    const btn = document.createElement("button");
                    btn.textContent = "❌";
                    btn.className = "ml-2 text-red-500 hover:text-red-700";
                    btn.addEventListener("click", () => {
                        allFiles.splice(index, 1);
                        syncFileInput();
                        updateFileList();
                    });

                    li.appendChild(span);
                    li.appendChild(btn);
                    fileList.appendChild(li);
                });
            }

            function syncFileInput() {
                const dataTransfer = new DataTransfer();
                allFiles.forEach(file => dataTransfer.items.add(file));
                fileInput.files = dataTransfer.files;
            }

            function addFiles(newFiles) {
                allFiles = [...allFiles, ...newFiles];
                syncFileInput();
                updateFileList();
            }

            dropZone.addEventListener("click", () => fileInput.click());
            dropZone.addEventListener("dragover", (e) => { e.preventDefault(); dropZone.classList.add("bg-blue-50","border-blue-400","text-blue-600"); });
            dropZone.addEventListener("dragleave", () => { dropZone.classList.remove("bg-blue-50","border-blue-400","text-blue-600"); });
            dropZone.addEventListener("drop", (e) => { e.preventDefault(); dropZone.classList.remove("bg-blue-50","border-blue-400","text-blue-600"); addFiles(e.dataTransfer.files); });
            fileInput.addEventListener("change", () => addFiles(fileInput.files));
        });
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
            // クリックされた要素が delete-button クラスを持つか確認
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
</body>
</html>
