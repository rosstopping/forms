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
    const tabButtons = [...tabs.querySelectorAll('[role="tab"][data-tab]')]
        .filter((button) => button.closest('[data-tabs]') === tabs);
    const tabPanels = [...tabs.querySelectorAll('[role="tabpanel"][data-tab-panel]')]
        .filter((panel) => panel.closest('[data-tabs]') === tabs);
    const storageKey = `selected-tab:${window.location.pathname}:${tabs.dataset.tabsKey || 'primary'}`;
    const availableTabs = tabButtons.map((button) => button.dataset.tab);

    const selectTab = (tabName, focus = false) => {
        if (!availableTabs.includes(tabName)) {
            return;
        }

        tabButtons.forEach((button) => {
            const selected = button.dataset.tab === tabName;

            button.setAttribute('aria-selected', selected.toString());
            button.tabIndex = selected ? 0 : -1;

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

    selectTab(defaultTab !== availableTabs[0] ? defaultTab : storedTab || defaultTab);
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

document.querySelectorAll('[data-seo-cost-estimate-form]').forEach((form) => {
    const keywords = form.querySelector('[data-seo-cost-keywords]');
    const depth = form.querySelector('[data-seo-cost-depth]');
    const output = form.querySelector('[data-seo-cost-output]');
    const costPerTen = Number(form.dataset.costPerTen);

    const updateEstimate = () => {
        const keywordCount = [...new Set(keywords.value
            .split(/[\n,]+/)
            .map((keyword) => keyword.trim().toLowerCase())
            .filter(Boolean))].length;
        const searchDepth = Math.min(100, Math.max(10, Number(depth.value) || 100));
        const estimate = keywordCount * Math.ceil(searchDepth / 10) * costPerTen;

        output.textContent = `$${estimate.toFixed(4)}`;
    };

    keywords.addEventListener('input', updateEstimate);
    depth.addEventListener('input', updateEstimate);
    updateEstimate();
});

document.querySelectorAll('[data-outreach-selection-form]').forEach((form) => {
    const selectAll = form.querySelector('[data-outreach-select-all]');
    const candidates = [...form.querySelectorAll('[data-outreach-candidate]:not(:disabled)')];

    if (!selectAll) {
        return;
    }

    const updateSelectAll = () => {
        const selectedCount = candidates.filter((candidate) => candidate.checked).length;

        selectAll.checked = candidates.length > 0 && selectedCount === candidates.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < candidates.length;
        selectAll.disabled = candidates.length === 0;
    };

    selectAll.addEventListener('change', () => {
        candidates.forEach((candidate) => {
            candidate.checked = selectAll.checked;
        });

        updateSelectAll();
    });

    candidates.forEach((candidate) => candidate.addEventListener('change', updateSelectAll));
    updateSelectAll();
});

const websiteAiForm = document.querySelector('[data-website-ai-form]');
const websiteAiMessages = document.querySelector('[data-website-ai-messages]');

if (websiteAiForm && websiteAiMessages) {
    const errorMessage = websiteAiForm.querySelector('[data-website-ai-error]');
    const questionInput = websiteAiForm.querySelector('textarea[name="question"]');
    const submitButton = websiteAiForm.querySelector('button[type="submit"]');
    const responseClasses = 'mr-8 whitespace-pre-line rounded-2xl rounded-bl-md bg-teal-50 px-3 py-2 text-sm leading-6 text-slate-700';
    const failureClasses = 'mr-8 rounded-2xl rounded-bl-md bg-red-50 px-3 py-2 text-sm text-red-700';

    const pollQuestion = async (article) => {
        const responseElement = article.querySelector('[data-website-ai-response]');

        for (let attempt = 0; attempt < 150 && article.dataset.status === 'processing'; attempt += 1) {
            await new Promise((resolve) => window.setTimeout(resolve, attempt === 0 ? 500 : 2000));

            try {
                const response = await fetch(article.dataset.statusUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error('Status request failed');
                }

                const result = await response.json();
                article.dataset.status = result.status;

                if (result.status === 'completed') {
                    responseElement.className = responseClasses;
                    responseElement.textContent = result.answer;
                } else if (result.status === 'failed') {
                    responseElement.className = failureClasses;
                    responseElement.textContent = result.error;
                }
            } catch {
                if (attempt === 149) {
                    responseElement.textContent = 'The answer is still processing. Reopen this chat shortly to check again.';
                }
            }
        }
    };

    document.querySelectorAll('[data-website-ai-question][data-status="processing"]').forEach(pollQuestion);

    websiteAiForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorMessage.classList.add('hidden');
        submitButton.disabled = true;

        try {
            const response = await fetch(websiteAiForm.action, {
                method: 'POST',
                body: new FormData(websiteAiForm),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || result.errors?.question?.[0] || 'The question could not be submitted.');
            }

            websiteAiMessages.querySelector('[data-website-ai-empty]')?.remove();
            const article = document.createElement('article');
            article.dataset.websiteAiQuestion = '';
            article.dataset.statusUrl = result.status_url;
            article.dataset.status = 'processing';
            article.className = 'space-y-2 px-4 py-3';
            const question = document.createElement('div');
            question.className = 'ml-8 rounded-2xl rounded-br-md bg-slate-100 px-3 py-2 text-sm text-slate-800';
            question.textContent = result.question.question;
            const pending = document.createElement('p');
            pending.dataset.websiteAiResponse = '';
            pending.className = 'text-sm text-slate-500';
            pending.textContent = 'Preparing an answer…';
            article.append(question, pending);
            websiteAiMessages.append(article);
            websiteAiMessages.scrollTop = websiteAiMessages.scrollHeight;
            websiteAiForm.reset();
            pollQuestion(article);
        } catch (error) {
            errorMessage.textContent = error.message;
            errorMessage.classList.remove('hidden');
        } finally {
            submitButton.disabled = false;
            questionInput.focus();
        }
    });
}

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

document.querySelectorAll('[data-bulk-leads-form]').forEach((form) => {
    const selectAll = form.querySelector('[data-bulk-leads-select-all]');
    const checkboxes = [...form.querySelectorAll('[data-bulk-leads-checkbox]')];
    const action = form.querySelector('[data-bulk-leads-action]');
    const selectionScope = form.querySelector('[data-bulk-leads-scope]');
    const actions = form.querySelector('[data-bulk-leads-actions]');
    const selectedCount = form.querySelector('[data-bulk-leads-count]');
    const selectionControl = form.querySelector('[data-bulk-leads-selection-control]');
    const selectionToggle = form.querySelector('[data-bulk-leads-selection-toggle]');
    const selectionMenu = form.querySelector('[data-bulk-leads-selection-menu]');
    const selectPage = form.querySelector('[data-bulk-leads-select-page]');
    const selectMatching = form.querySelector('[data-bulk-leads-select-matching]');
    const pageIndicator = form.querySelector('[data-bulk-leads-page-indicator]');
    const allIndicator = form.querySelector('[data-bulk-leads-all-indicator]');
    const menuToggle = form.querySelector('[data-bulk-leads-menu-toggle]');
    const menu = form.querySelector('[data-bulk-leads-menu]');
    const dialog = form.querySelector('[data-bulk-leads-dialog]');
    const dialogTitle = form.querySelector('[data-bulk-leads-dialog-title]');
    const dialogMessage = form.querySelector('[data-bulk-leads-dialog-message]');
    const statusField = form.querySelector('[data-bulk-leads-status-field]');
    const confirmButton = form.querySelector('[data-bulk-leads-confirm]');
    const totalMatching = Number(form.dataset.bulkLeadsTotal);
    let allMatching = false;

    const updateSelection = () => {
        const pageCount = checkboxes.filter((checkbox) => checkbox.checked).length;
        const count = allMatching ? totalMatching : pageCount;
        const wholePageSelected = checkboxes.length > 0 && pageCount === checkboxes.length;

        actions.classList.toggle('hidden', count === 0);
        actions.classList.toggle('flex', count > 0);
        selectedCount.textContent = count.toString();
        selectAll.checked = wholePageSelected;
        selectAll.indeterminate = pageCount > 0 && !wholePageSelected;
        selectionScope.value = allMatching ? 'all' : 'page';
        pageIndicator.classList.toggle('bg-teal-600', wholePageSelected && !allMatching);
        pageIndicator.classList.toggle('border-teal-600', wholePageSelected && !allMatching);
        allIndicator.classList.toggle('bg-teal-600', allMatching);
        allIndicator.classList.toggle('border-teal-600', allMatching);
    };

    selectAll?.addEventListener('change', () => {
        allMatching = false;
        checkboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
        updateSelection();
    });

    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
        allMatching = false;
        updateSelection();
    }));

    selectionToggle?.addEventListener('click', () => {
        const open = selectionMenu.classList.toggle('hidden') === false;

        selectionToggle.setAttribute('aria-expanded', open.toString());
    });

    selectPage?.addEventListener('click', () => {
        allMatching = false;
        checkboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });
        selectionMenu.classList.add('hidden');
        selectionToggle.setAttribute('aria-expanded', 'false');
        updateSelection();
    });

    selectMatching?.addEventListener('click', () => {
        allMatching = true;
        checkboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });
        selectionMenu.classList.add('hidden');
        selectionToggle.setAttribute('aria-expanded', 'false');
        updateSelection();
    });

    menuToggle?.addEventListener('click', () => {
        const open = menu.classList.toggle('hidden') === false;

        menuToggle.setAttribute('aria-expanded', open.toString());
    });

    form.querySelectorAll('[data-bulk-leads-open]').forEach((button) => button.addEventListener('click', () => {
        const selectedAction = button.dataset.bulkLeadsOpen;
        const count = checkboxes.filter((checkbox) => checkbox.checked).length;
        const content = {
            update_status: ['Update lead status?', `Choose the new status for ${count} selected lead${count === 1 ? '' : 's'}.`, 'Update status'],
            mark_spam: ['Mark leads as spam?', `${count} selected lead${count === 1 ? '' : 's'} will be hidden from the default inbox.`, 'Mark as spam'],
            delete: ['Delete selected leads?', `${count} selected lead${count === 1 ? '' : 's'} will be permanently deleted. This cannot be undone.`, 'Delete leads'],
        }[selectedAction];

        action.value = selectedAction;
        dialogTitle.textContent = content[0];
        dialogMessage.textContent = content[1];
        confirmButton.textContent = content[2];
        confirmButton.classList.toggle('bg-red-700', selectedAction === 'delete');
        confirmButton.classList.toggle('bg-slate-900', selectedAction !== 'delete');
        statusField.classList.toggle('hidden', selectedAction !== 'update_status');
        menu.classList.add('hidden');
        menuToggle.setAttribute('aria-expanded', 'false');
        dialog.showModal();
    }));

    form.querySelector('[data-bulk-leads-cancel]')?.addEventListener('click', () => dialog.close());
    document.addEventListener('click', (event) => {
        if (!selectionControl.contains(event.target)) {
            selectionMenu.classList.add('hidden');
            selectionToggle.setAttribute('aria-expanded', 'false');
        }

        if (!menu.contains(event.target) && !menuToggle.contains(event.target)) {
            menu.classList.add('hidden');
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    });
    updateSelection();
});

document.querySelectorAll('[data-confirm-action-dialog]').forEach((dialog) => {
    const title = dialog.querySelector('[data-confirm-action-title]');
    const message = dialog.querySelector('[data-confirm-action-message]');
    const submit = dialog.querySelector('[data-confirm-action-submit]');
    let activeForm = null;

    document.querySelectorAll('[data-confirm-action]').forEach((button) => button.addEventListener('click', () => {
        activeForm = button.closest('[data-confirm-action-form]');
        title.textContent = button.dataset.confirmTitle;
        message.textContent = button.dataset.confirmMessage;
        submit.textContent = button.dataset.confirmLabel;
        submit.classList.toggle('bg-red-700', button.hasAttribute('data-confirm-danger'));
        submit.classList.toggle('bg-slate-900', !button.hasAttribute('data-confirm-danger'));
        dialog.showModal();
    }));

    dialog.querySelector('[data-confirm-action-cancel]')?.addEventListener('click', () => dialog.close());
    submit.addEventListener('click', () => activeForm?.requestSubmit());
});

document.querySelectorAll('[data-progress-chart]').forEach((chart) => {
    const plot = chart.querySelector('[data-chart-plot]');
    const tooltip = chart.querySelector('[data-chart-tooltip]');
    const tooltipValue = chart.querySelector('[data-chart-tooltip-value]');
    const tooltipPeriod = chart.querySelector('[data-chart-tooltip-period]');
    const guide = chart.querySelector('[data-chart-guide]');
    const points = [...chart.querySelectorAll('[data-chart-point]')];

    if (!plot || !tooltip || !tooltipValue || !tooltipPeriod || !guide) {
        return;
    }

    const showPoint = (point) => {
        const pointBounds = point.getBoundingClientRect();
        const chartBounds = chart.getBoundingClientRect();
        const pointCenter = pointBounds.left + (pointBounds.width / 2) - chartBounds.left;
        const top = pointBounds.top - chartBounds.top - 8;

        tooltipValue.textContent = point.dataset.displayValue;
        tooltipPeriod.textContent = point.dataset.period;
        tooltip.style.setProperty('--chart-tooltip-top', `${top}px`);
        tooltip.classList.remove('hidden');
        const tooltipHalfWidth = tooltip.offsetWidth / 2;
        const left = Math.min(Math.max(pointCenter, tooltipHalfWidth + 4), chartBounds.width - tooltipHalfWidth - 4);

        tooltip.style.setProperty('--chart-tooltip-left', `${left}px`);
        guide.setAttribute('x1', point.dataset.chartX);
        guide.setAttribute('x2', point.dataset.chartX);
        guide.classList.remove('hidden');
    };

    const hidePoint = () => {
        tooltip.classList.add('hidden');
        guide.classList.add('hidden');
    };

    points.forEach((point) => {
        point.addEventListener('mouseenter', () => showPoint(point));
        point.addEventListener('mouseleave', hidePoint);
        point.addEventListener('focus', () => showPoint(point));
        point.addEventListener('blur', hidePoint);
        point.addEventListener('click', () => showPoint(point));
    });
});
