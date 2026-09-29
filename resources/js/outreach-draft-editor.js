export function initializeOutreachDraftEditors(root) {
    root.querySelectorAll('[data-outreach-draft-editor]').forEach((editor) => {
        const select = editor.querySelector('[data-outreach-template]');
        const apply = editor.querySelector('[data-outreach-template-apply]');
        const status = editor.querySelector('[data-outreach-template-status]');

        select.addEventListener('change', () => {
            apply.disabled = !select.value;
        });
        apply.addEventListener('click', () => {
            const option = select.selectedOptions[0];
            if (!option?.value) {
                return;
            }
            for (const field of ['subject', 'body']) {
                const input = editor.querySelector(`[name="outreach_${field}"]`);
                input.value = option.dataset[field];
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            status.textContent = `${option.textContent} loaded. Review the wording and save the draft before testing or approving.`;
            editor.querySelector('[name="outreach_subject"]').focus();
        });
    });
}
