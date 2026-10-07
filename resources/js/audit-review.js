import { emitAuditEngagement } from './audit-engagement.js';

export function bootAuditReview(document, target = window) {
    const dialog = document.querySelector('[data-audit-review-prompt]');
    if (!dialog?.showModal) return;

    const end = document.querySelector('[data-audit-snapshot-end]');
    const storageKey = `sitewell-audit-email-dismissed:${dialog.dataset.auditId}`;
    let stopped = false;
    const stop = () => {
        stopped = true;
        target.removeEventListener('scroll', onScroll);
        try { target.sessionStorage.setItem(storageKey, '1'); } catch {}
    };
    const open = () => {
        stop();
        if (document.visibilityState === 'visible' && !dialog.open) {
            dialog.showModal();
            emitAuditEngagement(document, 'review_opened');
        }
    };
    const onScroll = () => {
        if (stopped || target.scrollY < 32 || document.visibilityState !== 'visible') return;
        if (document.querySelector('dialog[open]')) return;
        if (end && end.getBoundingClientRect().top <= target.innerHeight - 80) open();
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
    target.addEventListener('scroll', onScroll, { passive: true });
}
