<?php
if (!isset($article)) {
    $article = ['id' => 0, 'title' => $_GET['title'] ?? '', 'content' => '', 'reference_for' => $_GET['reference_for'] ?? ''];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor Artikel | Wiki-Grok</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css?<?= time() ?>">
</head>
<body>
    <header>
        <nav>
            <ul>
                <li><a href="<?php echo BASE_URL; ?>">Beranda</a></a>
                <li><a href="<?php echo BASE_URL; ?>/article/editor">Buat Artikel Baru</a></li>
                <li>
            </ul>
        </nav>
    </header>
    <main>
        <h1>Buat atau Edit Artikel</h1>
        <form method="POST" action="<?php echo BASE_URL; ?>/article/save/<?php echo (int)$article['id']; ?>">
            <input type="text" name="title" id="title" placeholder="Judul Artikel" value="<?php echo htmlspecialchars($article['title']); ?>" required>
            <input type="hidden" name="reference_for" value="<?php echo htmlspecialchars($article['reference_for'] ?? ''); ?>">
            <div class="editor-toolbar">
                <a href="#" class="button" onclick="formatText('bold')">Tebal</a>
                <a href="#" class="button" onclick="formatText('italic')">Miring</a>
                <a href="#" class="button" onclick="formatText('heading')">Heading</a>
                <a href="#" class="button" onclick="formatText('link')">Link</a>
                <!-- New Buttons -->
                <a href="#" class="button" onclick="formatText('blockquote')">Blockquote</a>
                <a href="#" class="button" onclick="formatText('code')">Code</a>
                <a href="#" class="button" onclick="insertAutolink()">Autolink ID</a>
            </div>
            <div class="editor-content" id="editor" contenteditable="true" oninput="updateTextarea()"><?php echo htmlspecialchars($article['content']); ?></div>
            <textarea id="hidden-content" name="content" hidden></textarea>
            <div class="editor-toolbar-bottom">
                <button type="submit" name="save">Simpan</button>
                <a href="#" class="button" onclick="cancel()">Batal</a>
            </div>
        </form>
    </main>
    <script src="<?php echo BASE_URL; ?>/js/script.js.php?<?php echo time(); ?>"></script>
</body>
</html>