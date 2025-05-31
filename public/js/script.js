async function updateMissingAutolinks() {
    try {
        const response = await fetch('<?= BASE_URL; ?>/article/getMissingAutolinksAjax', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        if (data.missing_autolinks && data.missing_autolinks.length > 0) {
            const list = document.getElementById('missing-autolinks-list');
            list.innerHTML = data.missing_autolinks.map(title => `
                <li>
                    <span class="autolink-missing" data-title="${title}">
                        ${title}
                    </span>
                </li>
            `).join('');
            // Reattach event listeners
            document.querySelectorAll('.missing-autolinks .autolink-missing').forEach(link => {
                link.addEventListener('click', async e => {
                    e.preventDefault();
                    const title = link.getAttribute('data-title');
                    await showTooltip(link, title);
                });
            });
        } else {
            const section = document.querySelector('.missing-autolinks');
            if (section) section.remove();
        }
    } catch (error) {
        console.error('Error updating missing autolinks:', error);
    }
}