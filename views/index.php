<?php
$articles = isset($articles) ? $articles : [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$total_articles = count($articles); // Untuk paginasi sederhana
$total_pages = max(1, ceil($total_articles / $limit));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Daftar Artikel</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css?<?=time()?>">
</head>
<body>
    <div class="container">
        <h1>Daftar Artikel</h1>
        <div class="search-box">
            <form action="<?php echo BASE_URL; ?>/search" method="GET">
                <input type="text" name="q" class="search-input" placeholder="Cari artikel..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                <button type="submit" class="search-button">Cari</button>
            </form>
        </div>
        <p><a href="<?php echo BASE_URL; ?>/article/editor">Buat Artikel Baru</a></p>
        <?php if (empty($articles)): ?>
            <p>Tidak ada artikel yang ditemukan. Silakan buat artikel baru.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($articles as $article): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/article/view/<?php echo $article['id']; ?>">
                            <?php echo htmlspecialchars($article['title'] ?: 'Judul Kosong'); ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/article/editor/<?php echo $article['id']; ?>">[Edit]</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo BASE_URL; ?>?page=<?php echo $page - 1; ?>">Sebelumnya</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="<?php echo BASE_URL; ?>?page=<?php echo $i; ?>" <?php echo $i == $page ? 'style="font-weight: bold;"' : ''; ?>><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo BASE_URL; ?>?page=<?php echo $page + 1; ?>">Berikutnya</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <!-- Debug: Tampilkan jumlah artikel -->
        <p>Debug: Jumlah artikel yang diambil: <?php echo count($articles); ?></p>
    </div>
</body>
</html>