<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($article) ? 'Edit Artikel' : 'Buat Artikel'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css?<?=time()?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js"></script>
</head>
<body id="editor">
    <div class="container">
        <h1><?php echo isset($article) ? 'Edit Artikel' : 'Buat Artikel Baru'; ?></h1>
        <form action="<?php echo BASE_URL; ?>/article/save" method="POST" onsubmit="return validateForm()">
            <input type="hidden" name="article_id" value="<?php echo isset($article) ? $article['id'] : ''; ?>">
            <input type="hidden" name="reference_for" value="<?php echo isset($reference_for) ? htmlspecialchars($reference_for) : (isset($_GET['reference_for']) ? htmlspecialchars($_GET['reference_for']) : ''); ?>">
            <input type="text" id="title" name="title" value="<?php echo isset($article) ? htmlspecialchars($article['title']) : htmlspecialchars($_GET['title'] ?? ''); ?>" required>
            <div class="editor-toolbar" id="editorToolbar">
                <button type="button" title="Bold" onclick="formatText('bold')"><b>B</b></button>
                <button type="button" title="Italic" onclick="formatText('italic')"><i>I</i></button>
                <button type="button" title="Unordered List" onclick="formatText('insertUnorderedList')">•</button>
                <button type="button" title="Ordered List" onclick="formatText('insertOrderedList')">1.</button>
                <button type="button" title="Code" onclick="formatText('code')"><code></></code></button>
                <button type="button" title="Insert Image" onclick="triggerImageUpload()">🖼️</button>
                <input type="file" id="imageUpload" accept="image/*" style="display: none;" onchange="handleImageUpload(this)">
                <button type="button" title="Buat Autolink" onclick="createAutolink()">
                    <svg width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="4" width="3" height="8" fill="none" stroke="#000" stroke-width="1.5"/>
                        <rect x="11" y="4" width="3" height="8" fill="none" stroke="#000" stroke-width="1.5"/>
                        <path d="M5 8h6" stroke="#000" stroke-width="1.5"/>
                    </svg>
                </button>
                <button type="button" title="Judul (H1)" onclick="formatHeadingTitle('h1')">H1</button>
                <button type="button" title="Insert Break" onclick="insertBreak()">⏎</button>
                <button type="button" title="Garis Horizontal" onclick="insertHorizontalRule()">HR</button>
                <button type="button" title="Lihat Source Code" onclick="toggleSourceCode()">HTML</button>
            </div>
            <div id="content" contenteditable="true" class="editor-content"><?php echo isset($article) ? htmlspecialchars_decode($article['content']) : ''; ?></div>
            <textarea id="textContent" name="content" style="display: none;"></textarea>
            <textarea id="sourceCode" class="source-code" style="display: none;"></textarea>
            <div class="editor-toolbar-bottom" id="editorToolbarBottom">
                <button type="submit">Simpan</button>
                <a class="button right" href="<?php echo BASE_URL; ?>">Kembali ke Artikel</a>
            </div>
        </form>
    </div>
    <script>
        // Existing functions (unchanged unless specified)
        function cleanEmptyTags(content) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(content, 'text/html');
            const tagsToCheck = ['div', 'p', 'span', 'h1', 'h2', 'strong', 'b', 'i', 'code', 'li', 'th', 'td'];

            tagsToCheck.forEach(tag => {
                const elements = doc.querySelectorAll(tag);
                elements.forEach(el => {
                    if (!el.textContent.trim() && !el.querySelector('img, math, table')) {
                        el.remove();
                    } else if (tag === 'div' && el.children.length === 1 && (el.children[0].tagName === 'B' || el.children[0].tagName === 'STRONG')) {
                        const bold = el.children[0];
                        const h2 = document.createElement('h2');
                        h2.textContent = bold.textContent.trim();
                        el.parentNode.replaceChild(h2, el);
                    }
                });
            });
            const breaks = doc.querySelectorAll('div.break');
            for (let i = 1; i < breaks.length; i++) {
                if (breaks[i].previousSibling && breaks[i].previousSibling.nodeName === 'DIV' && breaks[i].previousSibling.classList.contains('break')) {
                    breaks[i].remove();
                }
            }

            return doc.body.innerHTML;
        }

        function saveContentTags() {
            const contentDiv = document.getElementById('content');
            let content = contentDiv.innerHTML;
            content = cleanEmptyTags(content);
            const textarea = document.createElement('textarea');
            textarea.innerHTML = content;
            content = textarea.value;
            document.getElementById('textContent').value = DOMPurify.sanitize(content, {
                ALLOWED_TAGS: ['h1', 'h2', 'b', 'i', 'ul', 'ol', 'li', 'div', 'hr', 'code', 'img', 'math', 'semantics', 'mrow', 'msub', 'mi', 'mo', 'annotation', 'table', 'tr', 'th', 'td'],
                ALLOWED_ATTR: ['src', 'class', 'xmlns', 'encoding', 'display'],
                KEEP_CONTENT: true,
                RETURN_TRUSTED_TYPE: false,
                SANITIZE_NAMED_PROPS: false,
                FORBID_TAGS: ['script', 'iframe', 'strong'],
                FORBID_ATTR: ['onerror', 'onload']
            });
        }

        function validateForm() {
            const title = document.getElementById('title').value.trim();
            saveContentTags();
            const content = document.getElementById('textContent').value.trim();
            if (!title || !content) {
                alert('Judul dan konten tidak boleh kosong!');
                return false;
            }
            return true;
        }

        function formatText(command) {
            if (command === 'bold') {
                const selection = window.getSelection();
                if (selection.rangeCount) {
                    const range = selection.getRangeAt(0);
                    const selectedText = range.toString().trim();
                    if (selectedText) {
                        const bold = document.createElement('b');
                        bold.textContent = selectedText;
                        range.deleteContents();
                        range.insertNode(bold);
                        selection.removeAllRanges();
                    }
                }
            } else if (command === 'code') {
                const selection = window.getSelection();
                if (selection.rangeCount) {
                    const range = selection.getRangeAt(0);
                    const selectedText = range.toString().trim();
                    if (selectedText) {
                        const code = document.createElement('code');
                        code.textContent = selectedText;
                        range.deleteContents();
                        range.insertNode(code);
                        selection.removeAllRanges();
                    }
                }
            } else if (command === 'insertUnorderedList' || command === 'insertOrderedList') {
                document.execCommand(command, false, null);
                const contentDiv = document.getElementById('content');
                const lists = contentDiv.querySelectorAll(command === 'insertUnorderedList' ? 'ul' : 'ol');
                lists.forEach(list => {
                    if (!list.querySelector('li')) {
                        const li = document.createElement('li');
                        li.textContent = 'Item';
                        list.appendChild(li);
                    }
                });
            } else {
                document.execCommand(command, false, null);
            }
            document.getElementById('content').focus();
        }

        function formatHeadingTitle(heading) {
            if (heading === 'h1') {
                const selection = window.getSelection();
                if (selection.rangeCount) {
                    const range = selection.getRangeAt(0);
                    const selectedText = range.toString().trim();
                    if (selectedText) {
                        const headingElement = document.createElement('h1');
                        headingElement.textContent = selectedText;
                        range.deleteContents();
                        range.insertNode(headingElement);
                        selection.removeAllRanges();
                    }
                }
            }
            document.getElementById('content').focus();
        }

        function insertHorizontalRule() {
            const selection = window.getSelection();
            if (selection.rangeCount) {
                const range = selection.getRangeAt(0);
                const hr = document.createElement('hr');
                range.insertNode(hr);
                range.setStartAfter(hr);
                range.setEndAfter(hr);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            document.getElementById('content').focus();
        }

        function insertBreak() {
            const selection = window.getSelection();
            if (selection.rangeCount) {
                const range = selection.getRangeAt(0);
                const previousNode = range.startContainer.previousSibling || (range.startContainer.parentNode && range.startContainer.parentNode.previousSibling);
                if (!(previousNode && previousNode.nodeName === 'DIV' && previousNode.classList.contains('break'))) {
                    const breakDiv = document.createElement('div');
                    breakDiv.className = 'break';
                    range.insertNode(breakDiv);
                    range.setStartAfter(breakDiv);
                    range.setEndAfter(breakDiv);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }
            document.getElementById('content').focus();
        }

        function toggleSourceCode() {
            const contentDiv = document.getElementById('content');
            const sourceCodeTextarea = document.getElementById('sourceCode');
            if (contentDiv.style.display !== 'none') {
                sourceCodeTextarea.value = cleanEmptyTags(contentDiv.innerHTML);
                contentDiv.style.display = 'none';
                sourceCodeTextarea.style.display = 'block';
            } else {
                contentDiv.innerHTML = DOMPurify.sanitize(cleanEmptyTags(sourceCodeTextarea.value), {
                    ALLOWED_TAGS: ['h1', 'h2', 'b', 'i', 'ul', 'ol', 'li', 'div', 'hr', 'code', 'img', 'math', 'semantics', 'mrow', 'msub', 'mi', 'mo', 'annotation', 'table', 'tr', 'th', 'td'],
                    ALLOWED_ATTR: ['src', 'class', 'xmlns', 'encoding', 'display'],
                    KEEP_CONTENT: true,
                    RETURN_TRUSTED_TYPE: false,
                    SANITIZE_NAMED_PROPS: false,
                    FORBID_TAGS: ['script', 'iframe', 'strong'],
                    FORBID_ATTR: ['onerror', 'onload']
                });
                contentDiv.style.display = 'block';
                sourceCodeTextarea.style.display = 'none';
            }
            document.getElementById('content').focus();
        }

        function triggerImageUpload() {
            document.getElementById('imageUpload').click();
        }

        function handleImageUpload(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '100%';
                    const editor = document.getElementById('content');
                    const range = window.getSelection().getRangeAt(0);
                    range.insertNode(img);
                    editor.focus();
                };
                reader.readAsDataURL(file);
            }
        }

        // Tooltip functions
        let activeTooltip = null;

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function showAutolinkTooltip(title, node) {
            if (activeTooltip) {
                activeTooltip.remove();
                activeTooltip = null;
            }

            if (!title || typeof title !== 'string') {
                console.warn('Invalid title:', title);
                return;
            }

            fetch('<?= BASE_URL; ?>/article/checkAutolink', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `title=${encodeURIComponent(title)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                let tooltipContent = '';

                if (data.exists) {
                    tooltipContent = `
                        <div class="tooltip-content">
                            <span class="tooltip-close">✖</span>
                            <h4>Artikel tersedia</h4>
                            <p>Artikel <b><a href="<?= BASE_URL; ?>/article/view/${escapeHtml(data.id)}">${escapeHtml(title)}</a></b> sudah ada.</p>
                        </div>
                    `;
                } else {
                    const similarArticles = data.similar_articles || [];
                    let similarList = '';
                    if (similarArticles.length > 0) {
                        similarList = `
                            <p>Judul serupa:</p>
                            <ul class="tooltip-related">
                                ${similarArticles.map(article => `
                                    <li>
                                        <a href="#" class="autolink-update" data-title="${encodeURIComponent(title)}" data-article-id="${article.id}">
                                            ${escapeHtml(article.title)}
                                        </a>
                                    </li>
                                `).join('')}
                            </ul>
                        `;
                    } else {
                        similarList = '<p class="no-results">Tidak ada judul serupa ditemukan.</p>';
                    }
                    tooltipContent = `
                        <div class="tooltip-content">
                            <span class="tooltip-close">✖</span>
                            <h4>Judul tidak tersedia</h4>
                            ${similarList}
                        </div>
                    `;
                }

                tooltip.innerHTML = tooltipContent;
                document.body.appendChild(tooltip);
                activeTooltip = tooltip;

                // Position tooltip
                let top = window.scrollY + 100;
                let left = window.scrollX + 100;
                const editor = document.getElementById('content');
                const editorRect = editor.getBoundingClientRect();
                if (node && node.nodeType === Node.TEXT_NODE && node.parentNode && document.contains(node)) {
                    try {
                        const range = document.createRange();
                        range.selectNodeContents(node);
                        const rect = range.getBoundingClientRect();
                        if (rect.width > 0 && rect.height > 0) {
                            top = rect.bottom + window.scrollY + 5;
                            left = rect.left + window.scrollX;
                        } else {
                            throw new Error('Invalid rect');
                        }
                    } catch (e) {
                        console.warn('Positioning failed for node:', node, e);
                        top = editorRect.top + window.scrollY + 20;
                        left = editorRect.left + window.scrollX;
                    }
                } else {
                    console.warn('Invalid or detached node:', node);
                    top = editorRect.top + window.scrollY + 20;
                    left = editorRect.left + window.scrollX;
                }
                tooltip.style.top = `${top}px`;
                tooltip.style.left = `${left}px`;

                // Close button handler
                tooltip.querySelector('.tooltip-close').addEventListener('click', (e) => {
                    e.stopPropagation();
                    tooltip.remove();
                    activeTooltip = null;
                });

                // Click-outside handler
                const closeTooltip = (e) => {
                    if (activeTooltip && !tooltip.contains(e.target) && (!node || (e.target !== node && (!node.contains || !node.contains(e.target))))) {
                        tooltip.remove();
                        activeTooltip = null;
                        document.removeEventListener('click', closeTooltip);
                    }
                };
                setTimeout(() => {
                    document.addEventListener('click', closeTooltip);
                }, 100);

                // Handle similar article clicks
                tooltip.querySelectorAll('.autolink-update').forEach(link => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        const linkTitle = decodeURIComponent(link.dataset.title);
                        const articleId = link.dataset.articleId;
                        updateAutolinkInEditor(linkTitle, articleId, node);
                        tooltip.remove();
                        activeTooltip = null;
                    });
                });
            })
            .catch(error => {
                console.error('Error fetching autolink data:', error);
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.innerHTML = `
                    <div class="tooltip-content">
                        <span class="tooltip-close">✖</span>
                        <h4>Kesalahan</h4>
                        <p class="no-results">Gagal memeriksa autolink untuk <b>${escapeHtml(title)}</b>.</p>
                    </div>
                `;
                document.body.appendChild(tooltip);
                activeTooltip = tooltip;

                const editor = document.getElementById('content');
                const editorRect = editor.getBoundingClientRect();
                tooltip.style.top = `${editorRect.top + window.scrollY + 20}px`;
                tooltip.style.left = `${editorRect.left + window.scrollX}px`;

                tooltip.querySelector('.tooltip-close').addEventListener('click', (e) => {
                    e.stopPropagation();
                    tooltip.remove();
                    activeTooltip = null;
                });
            });
        }

        function updateAutolinkInEditor(title, articleId) {
            const selection = window.getSelection();
            if (!selection.rangeCount) return;

            const range = selection.getRangeAt(0);
            const newAutolink = `[[${title}::${articleId}]]`;
            range.deleteContents();
            range.insertNode(document.createTextNode(newAutolink));
            document.getElementById('content').focus();
        }

        function detectAutolinks() {
            const selection = window.getSelection();
            if (!selection.rangeCount) return;

            const range = selection.getRangeAt(0);
            let node = range.startContainer;

            if (node.nodeType !== Node.TEXT_NODE) {
                if (node.childNodes[range.startOffset] && node.childNodes[range.startOffset].nodeType === Node.TEXT_NODE) {
                    node = node.childNodes[range.startOffset];
                } else {
                    return;
                }
            }

            if (!node || !node.parentNode || !document.contains(node)) {
                console.log('Invalid or detached node in detectAutolinks:', node);
                return;
            }

            const text = node.textContent;
            const offset = range.startOffset;
            const regex = /\[\[([^\]]*?)\]\]/g;
            let match;

            while ((match = regex.exec(text))) {
                const start = match.index;
                const end = start + match[0].length;
                if (offset >= start && offset <= end) {
                    const title = match[1].trim();
                    if (title) {
                        showAutolinkTooltip(title, node);
                    }
                    break;
                }
            }
        }

        function createAutolink() {
            const selection = window.getSelection();
            if (!selection.rangeCount) return;

            const range = selection.getRangeAt(0);
            const selectedText = range.toString().trim();
            if (selectedText) {
                const autolinkText = `[[${selectedText}]]`;
                range.deleteContents();
                const textNode = document.createTextNode(autolinkText);
                range.insertNode(textNode);
                if (textNode.parentNode && document.contains(textNode)) {
                    selection.removeAllRanges();
                    showAutolinkTooltip(selectedText, textNode);
                } else {
                    console.warn('Text node not attached or detached:', textNode);
                }
            }
            document.getElementById('content').focus();
        }

        // Event listeners
        document.getElementById('content').addEventListener('paste', function(e) {
            e.preventDefault();
            const clipboardData = e.clipboardData || window.clipboardData;
            let pastedData = clipboardData.getData('text/html') || clipboardData.getData('text/plain');

            if (pastedData.includes('<h')) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(pastedData, 'text/html');
                const headings = doc.querySelectorAll('h1, h2, h3, h4, h5, h6');
                headings.forEach(heading => {
                    const strong = heading.querySelector('strong');
                    const text = strong ? strong.textContent.trim() : heading.textContent.trim();
                    if (text) {
                        const newHeading = document.createElement('h1');
                        newHeading.textContent = text;
                        heading.parentNode.replaceChild(newHeading, heading);
                    } else {
                        heading.remove();
                    }
                });
                pastedData = cleanEmptyTags(doc.body.innerHTML);
            }

            const sanitizedData = DOMPurify.sanitize(pastedData, {
                ALLOWED_TAGS: ['h1', 'h2', 'b', 'i', 'ul', 'ol', 'li', 'div', 'hr', 'code', 'img', 'math', 'semantics', 'mrow', 'msub', 'mi', 'mo', 'annotation', 'table', 'tr', 'th', 'td'],
                ALLOWED_ATTR: ['src', 'class', 'xmlns', 'encoding', 'display'],
                KEEP_CONTENT: true,
                RETURN_TRUSTED_TYPE: false,
                SANITIZE_NAMED_PROPS: false,
                FORBID_TAGS: ['script', 'iframe', 'strong'],
                FORBID_ATTR: ['onerror', 'onload']
            });

            const range = window.getSelection().getRangeAt(0);
            range.deleteContents();
            const div = document.createElement('div');
            div.innerHTML = sanitizedData;
            range.insertNode(div);
            range.selectNodeContents(div);
            range.collapse(false);
        });

        document.getElementById('content').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const selection = window.getSelection();
                if (selection.rangeCount) {
                    const range = selection.getRangeAt(0);
                    const previousNode = range.startContainer.previousSibling || (range.startContainer.parentNode && range.startContainer.parentNode.previousSibling);
                    if (!(previousNode && previousNode.nodeName === 'DIV' && previousNode.classList.contains('break'))) {
                        const breakDiv = document.createElement('div');
                        breakDiv.className = 'break';
                        range.insertNode(breakDiv);
                        range.setStartAfter(breakDiv);
                        range.setEndAfter(breakDiv);
                        selection.removeAllRanges();
                        selection.addRange(range);
                    }
                }
                document.getElementById('content').focus();
            } else if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                formatText('bold');
            } else if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                createAutolink();
            }
        });

        document.getElementById('content').addEventListener('keyup', detectAutolinks);
        document.getElementById('content').addEventListener('click', detectAutolinks);

        const isAndroid = /Android/i.test(navigator.userAgent);
        if (isAndroid) {
            const contentDiv = document.getElementById('content');
            const toolbar = document.getElementById('editorToolbar');
            const toolbarBottom = document.getElementById('editorToolbarBottom');

            contentDiv.addEventListener('focus', function() {
                toolbar.classList.add('toolbar-android');
                toolbarBottom.classList.add('toolbar-android');
            });

            contentDiv.addEventListener('blur', function() {
                toolbar.classList.remove('toolbar-android');
                toolbarBottom.classList.remove('toolbar-android');
            });
        }
    </script>
</body>
</html>