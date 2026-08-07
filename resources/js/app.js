document.addEventListener('click', async (event) => {
    const button = event.target.closest('.js-copy-text');

    if (!button) {
        return;
    }

    const target = document.getElementById(button.dataset.copyTarget);

    if (!target) {
        return;
    }

    try {
        await navigator.clipboard.writeText(target.value);
    } catch {
        target.select();
        document.execCommand('copy');
        target.setSelectionRange(0, 0);
    }

    button.textContent = button.dataset.copiedLabel;

    window.setTimeout(() => {
        button.textContent = button.dataset.copyLabel;
    }, 2000);
});
