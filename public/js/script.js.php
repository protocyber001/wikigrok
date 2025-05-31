<?php
header('Content-Type: application/javascript');
?>
document.addEventListener('DOMContentLoaded', () => {
    // Existing tooltip code from views/article.php
    let activeTooltip = null;

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    // Editor functions
    window.formatText = (type) => {
        const editor = document.querySelector('.editor-content');
        if (!editor) return;

        const selection = window.getSelection();
        if (!selection.rangeCount) return;

        const range = selection.getRangeAt(0);
        const selectedText = range.toString();

        let newText = selectedText;
        switch (type) {
            case 'bold':
                newText = `**${selectedText}**`;
                break;
            case 'italic':
                newText = `*${selectedText}*`;
                break;
            case 'heading':
                newText = `# ${selectedText}`;
                break;
            case 'link':
                newText = `[${selectedText}](url)`;
                break;
            case 'blockquote': // New
                newText = `> ${selectedText}`;
                break;
            case 'code': // New
                newText = `\`\`\`\n${selectedText}\n\`\`\``;
                break;
        }

        const newNode = document.createTextNode(newText);
        range.deleteContents();
        range.insertNode(newNode);

        // Update hidden textarea
        updateTextarea();
    };

    window.insertAutolink = () => {
        const editor = document.querySelector('.editor-content');
        if (!editor) return;

        const title = prompt('Masukkan judul untuk autolink (e.g., Radio FM/AM):');
        if (!title) return;

        const id = prompt('Masukkan ID artikel (kosongkan jika tidak menggunakan ID):');
        const autolinkText = id && !isNaN(id) && id > 0 ? `[[${title}::${id}]]` : `[[${title}]]`;

        const selection = window.getSelection();
        const range = selection.rangeCount ? selection.getRangeAt(0) : null;
        if (range) {
            range.deleteContents();
            range.insertNode(document.createTextNode(autolinkText));
        } else {
            editor.appendChild(document.createTextNode(autolinkText));
        }

        // Update hidden textarea
        updateTextarea();
    };

    window.updateTextarea = () => {
        const editor = document.querySelector('.editor-content');
        const textarea = document.querySelector('#hidden-content');
        if (editor && textarea) {
            textarea.value = editor.innerText;
        }
    };

    window.cancel = () => {
        if (confirm('Batalkan perubahan?')) {
            window.location.href = '/';
        }
    };

    // Existing tooltip event listeners from views/article.php
    const article = document.querySelector('article');
    if (article) {
        article.addEventListener('click', async (e) => {
            const link = e.target.closest('.autolink-missing');
            if (link) {
                e.preventDefault();
                console.log('autolink-missing clicked in article:', link.textContent);
                const title = link.getAttribute('data-title') || link.textContent.trim();
                const currentArticleId = '<?php echo isset($article) ? $article['id'] : ''; ?>';
                await showTooltip(link, title, currentArticleId);
            }
        });
    }

    const missingAutolinksSection = document.querySelector('.missing-autolinks');
    if (missingAutolinksSection) {
        missingAutolinksSection.addEventListener('click', async (e) => {
            const link = e.target.closest('.autolink-missing');
            if (link) {
                e.preventDefault();
                console.log('autolink-missing clicked in homepage:', link.textContent);
                const title = link.getAttribute('data-title') || link.textContent.trim();
                await showTooltip(link, title);
            }
        });
    }

    // Existing showTooltip and createArticleFromAutolink functions
    const showTooltip = async (element, title, currentArticleId = '') => {
        // ... (unchanged from views/article.php, lines 151-205)
    };

    function createArticleFromAutolink(title, relatedTitle, referenceFor, callback = null) {
        // ... (unchanged from views/article.php, lines 251-260)
    }
});