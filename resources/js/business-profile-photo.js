export function initializeBusinessPostPhotos(root) {
    root.querySelectorAll('[data-business-post-form]').forEach((form) => {
        const input = form.querySelector('[data-business-post-photo]');
        const preview = form.querySelector('[data-business-post-photo-preview]');
        if (!input || !preview) return;

        const update = () => {
            const value = input.value.trim();
            const valid = value.startsWith('https://') && input.validity.valid;
            preview.hidden = !valid;
            if (valid) preview.src = value;
            else preview.removeAttribute('src');
        };
        input.addEventListener('input', update);
        preview.addEventListener('error', () => { preview.hidden = true; });
        update();
    });
}
