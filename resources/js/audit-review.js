import { emitAuditEngagement } from './audit-engagement.js';

export function bootAuditReview(document, target = window) {
    const dialog = document.querySelector('[data-audit-review-prompt]');
    if (!dialog?.showModal) return;

    const storageKey = `sitewell-audit-email-dismissed:${dialog.dataset.auditId}`;
    let stopped = false;
    let timer;
    let visibleSeconds = 0;
    const stop = () => {
        stopped = true;
        target.clearInterval(timer);
        try { target.sessionStorage.setItem(storageKey, '1'); } catch {}
    };
    const open = () => {
        stop();
        if (document.visibilityState === 'visible' && !dialog.open) {
            dialog.showModal();
            emitAuditEngagement(document, 'review_opened');
        }
    };

    document.querySelectorAll('[data-audit-email-open]').forEach(button => button.addEventListener('click', open));
    document.querySelectorAll('[data-audit-book-call]').forEach(button => button.addEventListener('click', stop));
    dialog.querySelector('[data-audit-email-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        stop();
        emitAuditEngagement(document, 'review_dismissed');
    });

    if (dialog.dataset.hasErrors === 'true') {
        open();
        return;
    }
    try { if (target.sessionStorage.getItem(storageKey) === '1') return; } catch {}
    timer = target.setInterval(() => {
        if (stopped || document.visibilityState !== 'visible' || document.querySelector('dialog[open]')) return;
        visibleSeconds++;
        if (visibleSeconds >= 30) open();
    }, 1000);
}
