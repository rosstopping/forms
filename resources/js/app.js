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

document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    const tabButtons = [...tabs.querySelectorAll('[role="tab"][data-tab]')];
    const tabPanels = [...tabs.querySelectorAll('[role="tabpanel"][data-tab-panel]')];
    const storageKey = `selected-tab:${window.location.pathname}`;
    const availableTabs = tabButtons.map((button) => button.dataset.tab);

    const selectTab = (tabName, focus = false) => {
        if (!availableTabs.includes(tabName)) {
            return;
        }

        tabButtons.forEach((button) => {
            const selected = button.dataset.tab === tabName;

            button.setAttribute('aria-selected', selected.toString());
            button.tabIndex = selected ? 0 : -1;
            button.classList.toggle('border-slate-900', selected);
            button.classList.toggle('text-slate-950', selected);
            button.classList.toggle('border-transparent', !selected);
            button.classList.toggle('text-slate-500', !selected);

            if (selected && focus) {
                button.focus();
            }
        });

        tabPanels.forEach((panel) => {
            panel.hidden = panel.dataset.tabPanel !== tabName;
        });

        window.sessionStorage.setItem(storageKey, tabName);
    };

    tabButtons.forEach((button, index) => {
        button.addEventListener('click', () => selectTab(button.dataset.tab));
        button.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            event.preventDefault();

            const nextIndex = event.key === 'Home'
                ? 0
                : event.key === 'End'
                    ? tabButtons.length - 1
                    : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabButtons.length) % tabButtons.length;

            selectTab(tabButtons[nextIndex].dataset.tab, true);
        });
    });

    const defaultTab = tabs.dataset.defaultTab;
    const storedTab = window.sessionStorage.getItem(storageKey);

    selectTab(defaultTab !== 'health' ? defaultTab : storedTab || defaultTab);
});

const mobileNavigation = document.querySelector('[data-mobile-nav]');
const mobileNavigationToggle = document.querySelector('[data-mobile-nav-toggle]');
const mobileNavigationCloseButtons = document.querySelectorAll('[data-mobile-nav-close]');

const setMobileNavigationOpen = (open) => {
    if (!mobileNavigation || !mobileNavigationToggle) {
        return;
    }

    mobileNavigation.classList.toggle('hidden', !open);
    mobileNavigationToggle.setAttribute('aria-expanded', open.toString());
    document.body.classList.toggle('overflow-hidden', open);
};

mobileNavigationToggle?.addEventListener('click', () => setMobileNavigationOpen(true));
mobileNavigationCloseButtons.forEach((button) => button.addEventListener('click', () => setMobileNavigationOpen(false)));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && mobileNavigation && !mobileNavigation.classList.contains('hidden')) {
        setMobileNavigationOpen(false);
        mobileNavigationToggle?.focus();
    }
});

document.querySelectorAll('[data-searchable-select]').forEach((combobox) => {
    const input = combobox.querySelector('[data-searchable-select-input]');
    const nativeSelect = combobox.querySelector('[data-searchable-select-native]');
    const optionsPanel = combobox.querySelector('[data-searchable-select-options]');
    const options = [...combobox.querySelectorAll('[data-searchable-select-option]')];
    const emptyMessage = combobox.querySelector('[data-searchable-select-empty]');
    const errorMessage = combobox.parentElement.querySelector('[data-searchable-select-error]');
    let activeIndex = -1;

    const visibleOptions = () => options.filter((option) => !option.hidden);
    const setOpen = (open) => {
        optionsPanel.classList.toggle('hidden', !open);
        input.setAttribute('aria-expanded', open.toString());
    };
    const setActiveOption = (index) => {
        const availableOptions = visibleOptions();

        activeIndex = availableOptions.length === 0 ? -1 : (index + availableOptions.length) % availableOptions.length;
        options.forEach((option) => option.classList.remove('bg-teal-50'));

        if (activeIndex >= 0) {
            availableOptions[activeIndex].classList.add('bg-teal-50');
            availableOptions[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    };
    const selectOption = (option) => {
        nativeSelect.value = option.dataset.value;
        input.value = option.dataset.label;
        input.setAttribute('aria-invalid', 'false');
        errorMessage.classList.add('hidden');
        options.forEach((candidate) => candidate.setAttribute('aria-selected', (candidate === option).toString()));
        nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        setOpen(false);
    };
    const filterOptions = () => {
        const query = input.value.trim().toLocaleLowerCase();
        let matches = 0;

        options.forEach((option) => {
            option.hidden = !option.dataset.label.toLocaleLowerCase().includes(query);
            matches += option.hidden ? 0 : 1;
        });

        emptyMessage?.classList.toggle('hidden', matches > 0);
        activeIndex = -1;
        setOpen(true);
    };

    input.addEventListener('focus', () => setOpen(true));
    input.addEventListener('input', () => {
        nativeSelect.value = '';
        options.forEach((option) => option.setAttribute('aria-selected', 'false'));
        filterOptions();
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setOpen(true);
            setActiveOption(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            selectOption(visibleOptions()[activeIndex]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    });
    options.forEach((option) => option.addEventListener('click', () => selectOption(option)));
    combobox.closest('form').addEventListener('submit', (event) => {
        if (nativeSelect.value !== '') {
            return;
        }

        event.preventDefault();
        input.setAttribute('aria-invalid', 'true');
        errorMessage.classList.remove('hidden');
        input.focus();
        filterOptions();
    });
    document.addEventListener('click', (event) => {
        if (!combobox.contains(event.target)) {
            setOpen(false);
        }
    });
});
