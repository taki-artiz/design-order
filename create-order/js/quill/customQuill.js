// quillのカスタムファイル
document.addEventListener('DOMContentLoaded', function() {
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
                modules: ['Resize', 'DisplaySize', 'Toolbar'] // 画像リサイズ用の機能を追加
            }
        }
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

    // テキストエリアの自動リサイズ
    const contentTextarea = document.getElementById('editor-container');
    contentTextarea.style.minHeight = '400px';
    // Quillのテキストを同期しつつ、自動で高さを調整
    quill.on('text-change', function () {
        contentTextarea.value = quill.root.innerHTML;

        // 高さ自動調整の実行を遅延 (次のイベントループで確実に更新)
        setTimeout(() => {
            contentTextarea.style.height = 'auto';  // リセット
            contentTextarea.style.height = contentTextarea.scrollHeight + 'px';  // 新しい高さを適用
        }, 0);
    });

    // 初回ロード時も高さを調整
    setTimeout(() => {
        contentTextarea.style.height = 'auto';
        contentTextarea.style.height = contentTextarea.scrollHeight + 'px';
    }, 0);

    // ファイル選択時の表示更新
    document.getElementById('thumbnail').addEventListener('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : '選択されていません';
        e.target.nextElementSibling.textContent = fileName;
    });

    // ★同期先を「#content」に変更する
    const hiddenTextarea = document.getElementById('content');
    quill.clipboard.dangerouslyPasteHTML(initialContent);
    hiddenTextarea.value = quill.root.innerHTML;

    // Quill の内容が変わるたびに textarea にコピー
    quill.on('text-change', function() {
        hiddenTextarea.value = quill.root.innerHTML;
    });

    // DBから取得した本文を初期値として設定
    quill.root.innerHTML = initialContent;

    // フォーム送信時に textarea に反映
    const form = document.querySelector('form');
    const textarea = document.getElementById('content');
    form.addEventListener('submit', () => {
        textarea.value = quill.root.innerHTML;
    });
});