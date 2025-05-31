<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Revisi</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css?<?=time()?>">
</head>
<body>
    <div class="container">
        <h1>Riwayat Revisi: <?php echo htmlspecialchars($article['title']); ?></h1>
        <a href="<?php echo BASE_URL; ?>/article/view/<?php echo $article['id']; ?>">Kembali ke Artikel</a>
        <ul>
            <?php foreach ($revisions as $revision): ?>
                <li>
                    <p><strong>Diperbarui: <?php echo $revision['revised_at']; ?></strong></p>
                    <p><strong>Judul:</strong> <?php echo htmlspecialchars($revision['title']); ?></p>
                    <div><?php echo (new Article())->processAutolinks($revision['content']); ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let activeTooltip = null;

            document.querySelectorAll('.autolink-missing').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();

                    // Tutup tooltip aktif jika ada
                    if (activeTooltip) {
                        activeTooltip.remove();
                        activeTooltip = null;
                    }

                    const title = link.textContent;
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.innerHTML = `
                        <div class="tooltip-content">
                            <span class="tooltip-close">✖</span>
                            <h4>Judul belum tersedia</h4>
                            <p><a href="<?php echo BASE_URL; ?>/article/editor?title=${encodeURIComponent(title)}">Buat artikel "${title}"</a></p>
                            <p><a href="https://chat.openai.com/?q=${encodeURIComponent(title)}" target="_blank">Cari "${title}" di ChatGPT</a></p>
                        </div>
                    `;
                    document.body.appendChild(tooltip); // Tambahkan ke body untuk position: fixed

                    activeTooltip = tooltip;

                    // Tambah event listener untuk tanda silang
                    tooltip.querySelector('.tooltip-close').addEventListener('click', () => {
                        tooltip.remove();
                        activeTooltip = null;
                    });

                    // Tutup tooltip saat klik di luar
                    document.addEventListener('click', function closeTooltip(e) {
                        if (!tooltip.contains(e.target) && e.target !== link) {
                            tooltip.remove();
                            activeTooltip = null;
                            document.removeEventListener('click', closeTooltip);
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>