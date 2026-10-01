export function initializeHomeReveals(document, viewport = window) {
    const items = [...document.querySelectorAll('[data-home-reveal]')];

    if (items.length === 0 || !viewport.IntersectionObserver || viewport.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const observer = new viewport.IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-revealed');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -40px 0px', threshold: 0.05 });

    document.documentElement.classList.add('home-reveal-ready');
    items.forEach((item) => observer.observe(item));
}
