<!-- ドキュメントルートを取得 -->
<?php
$base_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
?>
<aside class="relative min-w-[150px] bg-gray-700 text-white z-[20]">
    <div class="fixed h-screen">
        <div class="w-[150px] p-4 font-bold">管理コンテンツ</div>
        <div class="w-[150px] border-t border-gray-600">
            <?php
            if($_COOKIE['create_order'] === "artiz-creative"){
                $topPage = "https://artiz-app.com/create-order/edit/creator_view.php";
            }else{
                $topPage = "https://artiz-app.com/create-order/edit/orders_view.php";
            }
            ?>
            <?php if($_COOKIE['account_role'] === "admin"): ?>
                <a href="<?= $topPage ?>" class="pt-6 pb-4 px-2 flex items-center justify-between hover:bg-gray-600">
                    <span>▶ 制作依頼</span>
                </a>
                <a href="https://artiz-app.com/create-order/analytics/analytics.php" class="py-4 px-2 flex items-center justify-between hover:bg-gray-600">
                    <span>▶ 制作集計</span>
                </a>
                <a href="https://artiz-app.com/create-order/cost_table/cost_table.php" class="py-4 px-2 flex items-center justify-between hover:bg-gray-600">
                    <span class="whitespace-nowrap">▶ 原価・外注価格</span>
                </a>
                <a href="https://artiz-app.com/create-order/sign_up/sign_up.php" class="py-4 px-2 flex items-center justify-between hover:bg-gray-600">
                    <span class="whitespace-nowrap text-[15px]">▶ アカウント管理発行</span>
                </a>
                <a href="" class="absolute bottom-[75px] py-4 px-2 w-[150px] flex items-center justify-between hover:bg-gray-600">
                    <span class="whitespace-nowrap">▶ 使い方(未実装)</span>
                </a>
                <a href="#"
                    class="logout-btn absolute bottom-[20px] py-4 px-2 w-[150px] flex items-center justify-between hover:bg-gray-600 ">
                    <span class="whitespace-nowrap">▶ ログアウト</span>
                </a>

            <?php endif ?>
            <?php if($_COOKIE['account_role'] === "order"): ?>
                <a href="<?= $topPage ?>" class="pt-6 pb-4 px-4 flex items-center justify-between hover:bg-gray-600 whitespace-nowrap">
                    <span>▶ トップページへ</span>
                </a>
                <a href="../how_to_use/orders.php" class="p-4 flex items-center justify-between hover:bg-gray-600">
                    <span class="whitespace-nowrap">▶ 使い方</span>
                </a>
                <a href="#"
                    class="logout-btn absolute bottom-[20px] py-4 px-2 w-[150px] flex items-center justify-between hover:bg-gray-600 ">
                    <span class="whitespace-nowrap">▶ ログアウト</span>
                </a>
            <?php endif ?>
        </div>
    </div>

    <script>
        // ログアウトボタンのクリックイベント
        document.addEventListener('DOMContentLoaded', function() {
        const logoutBtn = document.querySelector('.logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Cookie削除（max-age=0で即時無効化）
            document.cookie = "create_order=; path=/; max-age=0;";
            document.cookie = "account_role=; path=/; max-age=0;";
            // リダイレクト先
            window.location.href = "https://artiz-app.com/create-order/login.php";
            });
        }
        });
    </script>
</aside>