<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiki Artikel</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/css/style.css?<?= time() ?>">
</head>
<body>
    <header>
        <nav aria-label="Navigasi utama">
            <ul>
                <li><a href="<?= BASE_URL; ?>" aria-current="page">Beranda</a></li>
                <li><a href="<?= BASE_URL; ?>/article/editor">Buat Artikel Baru</a></li>
                <li class="search-box">
                    <form action="<?= BASE_URL; ?>" method="GET">
                        <input type="text" name="search" placeholder="Masukkan kata kunci untuk mencari artikel..." value="<?= htmlspecialchars($_GET['search'] ?? ''); ?>" class="search-input">
                        <select name="sort" class="sort-select">
                            <option value="created_at" <?= ($_GET['sort'] ?? '') === 'created_at' ? 'selected' : ''; ?>>Terbaru</option>
                            <option value="view_count" <?= ($_GET['sort'] ?? '') === 'view_count' ? 'selected' : ''; ?>>Jumlah Tayang</option>
                            <option value="autolinks" <?= ($_GET['sort'] ?? '') === 'autolinks' ? 'selected' : ''; ?>>Jumlah Autolink</option>
                            <option value="incoming_autolinks" <?= ($_GET['sort'] ?? '') === 'incoming_autolinks' ? 'selected' : ''; ?>>Autolink Masuk</option>
                        </select>
                        <button type="submit" class="search-button">Cari</button>
                    </form>
                </li>
            </ul>
        </nav>
    </header>
    <main>
        <h1>Wiki-Grok</h1>
        <p>Eksplorasi Tanpa Batas, Wawasan dari Jaringan Artikel.</p>
        <?php if (!isset($article) && !empty($missing_autolinks)): ?>
            <section class="missing-autolinks">
                <h3>Topik yang Perlu Artikel</h3>
                <p>Klik judul di bawah untuk melihat opsi:</p>
                <ul id="missing-autolinks-list">
                    <?php foreach ($missing_autolinks as $missing_title): ?>
                        <li>
                            <span class="autolink-missing" data-title="<?= htmlspecialchars($missing_title); ?>">
                                <?= htmlspecialchars($missing_title); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <article>
            <?php if (isset($article)): ?>
                <section class="header">
                    <h1><?= htmlspecialchars($article['title']); ?></h1>
                </section>
                <section class="article-content">
                    <?= (new Article())->processAutolinks(htmlspecialchars_decode($article['content'])); ?>
                </section>
                <section class="article-meta">
                    <p><em>Dibuat: <?= $article['created_at']; ?></em></p>
                </section>
                <section class="session-admin article-moderation">
                    <div class="actions">
                        <a class="button" href="<?= BASE_URL; ?>/article/editor/<?= $article['id']; ?>">Edit</a>
                        <a class="button" href="<?= BASE_URL; ?>/article/revisions/<?= $article['id']; ?>">Lihat Riwayat Revisi</a>
                    </div>
                </section>
                <section class="session-admin article-statistics" itemscope itemtype="https://schema.org/Article">
                    <h2>Statistik Artikel</h2>
                    <dl>
                        <dt>Jumlah Tayangan</dt>
                        <dd itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
                            <meta itemprop="interactionType" content="https://schema.org/ViewAction">
                            <span itemprop="userInteractionCount"><?= htmlspecialchars($article['view_count'] ?? 0); ?></span>
                        </dd>
                        <dt>Autolink Keluar</dt>
                        <dd itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
                            <meta itemprop="interactionType" content="https://schema.org/ShareAction">
                            <span itemprop="userInteractionCount"><?= htmlspecialchars($article['autolink_count'] ?? 0); ?></span>
                        </dd>
                        <dt>Autolink Masuk</dt>
                        <dd itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
                            <meta itemprop="interactionType" content="https://schema.org/ShareAction">
                            <span itemprop="userInteractionCount"><?= htmlspecialchars($article['incoming_autolink_count'] ?? 0); ?></span>
                        </dd>
                        <dt>Autolink Tidak Tersedia</dt>
                        <dd itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
                            <meta itemprop="interactionType" content="https://schema.org/ShareAction">
                            <span itemprop="userInteractionCount"><?= htmlspecialchars($article['missing_autolink_count'] ?? 0); ?></span>
                        </dd>
                    </dl>
                </section>
                <?php if (!empty($related_articles)): ?>
                    <section class="related-articles-section">
                        <h3>Artikel Terkait</h3>
                        <small>Judul ini menjadi autolink di dalam konten di artikel lain.</small>
                        <ul class="related-articles">
                            <?php foreach ($related_articles as $related): ?>
                                <li>
                                    <a href="<?= BASE_URL; ?>/article/view/<?= $related['id']; ?>">
                                        <?= htmlspecialchars($related['title']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            <?php else: ?>
                <section class="article-list-section">
                    <h3><?= isset($_GET['search']) && trim($_GET['search']) !== '' ? 'Hasil untuk: "' . htmlspecialchars($_GET['search']) . '"' : 'Daftar Artikel'; ?></h3>
                    <?php if (empty($articles) && isset($_GET['search']) && trim($_GET['search']) !== ''): ?>
                        <p class="no-results">Tidak ada artikel yang ditemukan untuk "<?= htmlspecialchars($_GET['search']); ?>".</p>
                    <?php elseif (empty($articles)): ?>
                        <p class="no-results">Belum ada artikel yang tersedia.</p>
                    <?php else: ?>
                        <ul class="article-list">
                            <?php 
                            $query = trim($_GET['search'] ?? '');
                            foreach ($articles as $art): 
                                $title = $query ? preg_replace("/($query)/i", '<mark>$1</mark>', htmlspecialchars($art['title'])) : htmlspecialchars($art['title']);
                            ?>
                                <li>
                                    <a href="<?= BASE_URL; ?>/article/view/<?= $art['id']; ?>">
                                        <?= $title; ?>
                                    </a>
                                    <a href="<?= BASE_URL; ?>/article/revisions/<?= $art['id']; ?>">(Riwayat)</a>
                                    <div class="article-stats session-admin">
                                        <span>Tayang: <strong><?= htmlspecialchars($art['view_count'] ?? 0); ?></strong></span>
                                        <span>Autolink: <strong><?= htmlspecialchars($art['incoming_autolink_count'] ?? 0); ?>/<?= htmlspecialchars($art['autolink_count'] ?? 0); ?></strong></span>
                                        <span>Autolink Tidak Tersedia: <strong><?= htmlspecialchars($art['missing_autolink_count'] ?? 0); ?></strong></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </article>
    </main>
    <script src="<?= BASE_URL; ?>/js/script.js?<?= time() ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let activeTooltip = null;

            // Helper function to escape HTML
            const escapeHtml = (str) => {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            };

            // Function to show tooltip
            const showTooltip = async (element, title, currentArticleId = '') => {
                console.log('showTooltip called for title:', title); // Debug log
                if (activeTooltip) {
                    activeTooltip.remove();
                    activeTooltip = null;
                }

                let relatedArticles = [];
                try {
                    console.log('Fetching related articles for:', title); // Debug log
                    const response = await fetch('<?= BASE_URL; ?>/article/searchRelevantTitles', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `phrase=${encodeURIComponent(title)}`
                    });
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    relatedArticles = await response.json();
                    console.log('Related articles:', relatedArticles); // Debug log
                } catch (error) {
                    console.error('Error fetching related articles:', error);
                    relatedArticles = []; // Fallback to empty array
                }

                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                let relatedList = '';
                if (relatedArticles.length > 0) {
                    relatedList = `
                        <p>Artikel lain:</p>
                        <ul class="tooltip-related">
                            ${relatedArticles.map(article => `
                                <li>
                                    <a href="#" onclick="createArticleFromAutolink('${encodeURIComponent(title)}', '${encodeURIComponent(article.title)}', '${currentArticleId}', () => updateMissingAutolinks())">
                                        ${escapeHtml(article.title)}
                                    </a>
                                </li>
                            `).join('')}
                        </ul>
                    `;
                } else {
                    relatedList = '<p class="no-results">Tidak ada artikel lain ditemukan.</p>';
                }

                tooltip.innerHTML = `
                    <div class="tooltip-content">
                        <span class="tooltip-close">✖</span>
                        <h4>Judul tidak tersedia</h4>
                        <ul>
                            <li class="articletitle">
                                <h4>${title}</h4>
                            </li>
                            <li><a href="<?= BASE_URL; ?>/article/editor/?title=${encodeURIComponent(title)}&reference_for=${currentArticleId}" onclick="updateMissingAutolinks()">Buat artikel</a></li>
                            <li><a href="https://chat.openai.com/?q=<?php echo defined('QUERY_TO_CHATBOT') ? QUERY_TO_CHATBOT : 'Penjelasan tentang'; ?>${encodeURIComponent(title)}" target="_blank">Cari di ChatGPT</a></li>
                            <li><a href="https://grok.com/?q=<?php echo defined('QUERY_TO_CHATBOT') ? QUERY_TO_CHATBOT : 'Penjelasan tentang'; ?>${encodeURIComponent(title)}" target="_blank">Cari di Grok</a></li>
                        </ul>
                        ${relatedList}
                    </div>
                `;

                // Position tooltip near the clicked element
                // const rect = element.getBoundingClientRect();
                // tooltip.style.position = 'absolute';
                // tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
                // tooltip.style.left = `${rect.left + window.scrollX}px`;

                document.body.appendChild(tooltip);
                activeTooltip = tooltip;

                // Close button handler
                tooltip.querySelector('.tooltip-close').addEventListener('click', () => {
                    tooltip.remove();
                    activeTooltip = null;
                });

                // Click-outside handler
                document.addEventListener('click', function closeTooltip(e) {
                    if (!tooltip.contains(e.target) && e.target !== element) {
                        tooltip.remove();
                        activeTooltip = null;
                        document.removeEventListener('click', closeTooltip);
                    }
                }, { once: true });
            };

            // Event delegation for autolink-missing in article content
            document.querySelector('article').addEventListener('click', async (e) => {
                const link = e.target.closest('.autolink-missing');
                if (link) {
                    e.preventDefault();
                    console.log('autolink-missing clicked in article:', link.textContent); // Debug log
                    const title = link.getAttribute('data-title') || link.textContent.trim();
                    const currentArticleId = '<?php echo isset($article) ? $article['id'] : ''; ?>';
                    await showTooltip(link, title, currentArticleId);
                }
            });

            // Event delegation for autolink-missing in homepage notifications
            const missingAutolinksSection = document.querySelector('.missing-autolinks');
            if (missingAutolinksSection) {
                missingAutolinksSection.addEventListener('click', async (e) => {
                    const link = e.target.closest('.autolink-missing');
                    if (link) {
                        e.preventDefault();
                        console.log('autolink-missing clicked in homepage:', link.textContent); // Debug log
                        const title = link.getAttribute('data-title') || link.textContent.trim();
                        await showTooltip(link, title);
                    }
                });
            }

            // Function to update missing autolinks list
            function updateMissingAutolinks() {
                const missingList = document.getElementById('missing-autolinks-list');
                if (!missingList) return;
                fetch('<?= BASE_URL; ?>/article/getMissingAutolinks')
                    .then(response => response.json())
                    .then(data => {
                        missingList.innerHTML = data.map(title => `
                            <li>
                                <span class="autolink-missing" data-title="${escapeHtml(title)}">
                                    ${escapeHtml(title)}
                                </span>
                            </li>
                        `).join('');
                    })
                    .catch(error => console.error('Error updating missing autolinks:', error));
            }
        });

        function createArticleFromAutolink(title, relatedTitle, referenceFor, callback = null) {
            console.log('createArticleFromAutolink called:', { title, relatedTitle, referenceFor }); // Debug log
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= BASE_URL; ?>/article/createFromAutolink';
            form.innerHTML = `
                <input type="hidden" name="title" value="${decodeURIComponent(title)}">
                <input type="hidden" name="related_title" value="${decodeURIComponent(relatedTitle)}">
                <input type="hidden" name="reference_for" value="${referenceFor}">
            `;
            document.body.appendChild(form);
            form.submit();
            if (callback) callback();
        }
    </script>
</body>
</html>