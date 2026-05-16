import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const statusDropdowns = Array.from(document.querySelectorAll('[data-status-multi-select]'));

    statusDropdowns.forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-status-trigger]');
        const menu = dropdown.querySelector('[data-status-menu]');
        const label = dropdown.querySelector('[data-status-label]');
        const icon = dropdown.querySelector('[data-status-icon]');
        const clearButton = dropdown.querySelector('[data-status-clear]');
        const checkboxes = Array.from(dropdown.querySelectorAll('[data-status-checkbox]'))
            .filter((checkbox) => checkbox instanceof HTMLInputElement);

        if (!(trigger instanceof HTMLButtonElement) || !(menu instanceof HTMLElement) || !(label instanceof HTMLElement)) {
            return;
        }

        const selectedValues = () => checkboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);

        const updateLabel = () => {
            const values = selectedValues();

            if (values.length === 0) {
                label.textContent = 'All';
                return;
            }

            if (values.length <= 2) {
                label.textContent = values.join(', ');
                return;
            }

            label.textContent = `${values.length} selected`;
        };

        const closeMenu = () => {
            menu.classList.add('hidden');
            trigger.setAttribute('aria-expanded', 'false');
            icon?.classList.remove('rotate-180');
        };

        const openMenu = () => {
            menu.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
            icon?.classList.add('rotate-180');
        };

        const isOpen = () => !menu.classList.contains('hidden');

        trigger.addEventListener('click', (event) => {
            event.preventDefault();

            if (isOpen()) {
                closeMenu();
                return;
            }

            statusDropdowns.forEach((item) => {
                if (item === dropdown) {
                    return;
                }

                const itemTrigger = item.querySelector('[data-status-trigger]');
                const itemMenu = item.querySelector('[data-status-menu]');
                const itemIcon = item.querySelector('[data-status-icon]');

                if (itemMenu instanceof HTMLElement) {
                    itemMenu.classList.add('hidden');
                }

                if (itemTrigger instanceof HTMLButtonElement) {
                    itemTrigger.setAttribute('aria-expanded', 'false');
                }

                itemIcon?.classList.remove('rotate-180');
            });

            openMenu();
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateLabel);
        });

        if (clearButton instanceof HTMLButtonElement) {
            clearButton.addEventListener('click', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });

                updateLabel();
            });
        }

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Node) || dropdown.contains(event.target)) {
                return;
            }

            closeMenu();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen()) {
                closeMenu();
            }
        });

        updateLabel();
    });

    Array.from(document.querySelectorAll('[data-bulk-delete-form]')).forEach((form) => {
        const selectAll = form.querySelector('[data-bulk-select-all]');
        const rowCheckboxes = Array.from(form.querySelectorAll('[data-bulk-select-row]'))
            .filter((checkbox) => checkbox instanceof HTMLInputElement);
        const submitButtons = Array.from(form.querySelectorAll('[data-bulk-submit]'))
            .filter((button) => button instanceof HTMLButtonElement);
        const selectedCount = form.querySelector('[data-bulk-selected-count]');

        const enabledCheckboxes = rowCheckboxes.filter((checkbox) => !checkbox.disabled);

        const syncBulkState = () => {
            const checkedCount = enabledCheckboxes.filter((checkbox) => checkbox.checked).length;

            submitButtons.forEach((button) => {
                button.disabled = checkedCount === 0;
            });

            if (selectedCount instanceof HTMLElement) {
                selectedCount.textContent = `${checkedCount} selected`;
            }

            if (selectAll instanceof HTMLInputElement) {
                selectAll.checked = enabledCheckboxes.length > 0 && checkedCount === enabledCheckboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < enabledCheckboxes.length;
                selectAll.disabled = enabledCheckboxes.length === 0;
            }
        };

        if (selectAll instanceof HTMLInputElement) {
            selectAll.addEventListener('change', () => {
                enabledCheckboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });

                syncBulkState();
            });
        }

        enabledCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncBulkState);
        });

        form.addEventListener('submit', (event) => {
            const checkedCount = enabledCheckboxes.filter((checkbox) => checkbox.checked).length;
            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            const confirmMessage = submitter?.getAttribute('data-bulk-confirm')
                || form.getAttribute('data-bulk-confirm')
                || 'Delete selected records?';

            if (checkedCount === 0 || !window.confirm(confirmMessage)) {
                event.preventDefault();
            }
        });

        syncBulkState();
    });

    const navigationWrappers = Array.from(document.querySelectorAll('[data-service-desk-nav]'));

    navigationWrappers.forEach((wrapper) => {
        const toggleButton = wrapper.querySelector('[data-service-desk-toggle]');
        const closeButton = wrapper.querySelector('[data-service-desk-close]');
        const overlay = wrapper.querySelector('[data-service-desk-overlay]');
        const drawer = wrapper.querySelector('[data-service-desk-drawer]');
        const drawerLinks = Array.from(wrapper.querySelectorAll('[data-service-desk-link]'));

        if (!toggleButton || !overlay || !drawer) {
            return;
        }

        const setOpen = (shouldOpen) => {
            wrapper.classList.toggle('is-open', shouldOpen);
            document.body.classList.toggle('service-desk-nav-open', shouldOpen);
            toggleButton.setAttribute('aria-expanded', String(shouldOpen));
            toggleButton.setAttribute(
                'aria-label',
                shouldOpen ? 'Close service desk navigation' : 'Open service desk navigation'
            );
            drawer.setAttribute('aria-hidden', String(!shouldOpen));
        };

        const closeDrawer = () => setOpen(false);

        toggleButton.addEventListener('click', () => {
            setOpen(!wrapper.classList.contains('is-open'));
        });

        closeButton?.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);
        drawerLinks.forEach((link) => link.addEventListener('click', closeDrawer));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && wrapper.classList.contains('is-open')) {
                closeDrawer();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024 && wrapper.classList.contains('is-open')) {
                closeDrawer();
            }
        });
    });

    const hasChatRuntime = document.querySelector('[data-project-chat-thread], [data-project-chat-message], [data-project-chat-form]');

    if (hasChatRuntime) {
        import('./chat-runtime')
            .then(({ initChatRuntime }) => initChatRuntime())
            .catch((error) => {
                console.error('Failed to load chat runtime.', error);
            });
    }
});
