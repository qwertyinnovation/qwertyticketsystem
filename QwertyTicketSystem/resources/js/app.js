import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
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

    const chatThread = document.querySelector('[data-project-chat-thread]');
    const chatMessageField = document.querySelector('[data-project-chat-message]');
    const chatForm = document.querySelector('[data-project-chat-form]');

    if (chatThread instanceof HTMLElement) {
        chatThread.scrollTop = chatThread.scrollHeight;

        window.setInterval(() => {
            const hasDraftMessage = chatMessageField instanceof HTMLTextAreaElement
                && chatMessageField.value.trim() !== '';

            if (document.hidden || hasDraftMessage || document.activeElement === chatMessageField) {
                return;
            }

            window.location.reload();
        }, 15000);
    }

    if (chatMessageField instanceof HTMLTextAreaElement) {
        const syncComposerHeight = () => {
            chatMessageField.style.height = '0px';
            chatMessageField.style.height = `${Math.min(chatMessageField.scrollHeight, 160)}px`;
        };

        syncComposerHeight();
        chatMessageField.addEventListener('input', syncComposerHeight);
        chatMessageField.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.shiftKey) {
                return;
            }

            event.preventDefault();

            if (chatMessageField.value.trim() === '' || !(chatForm instanceof HTMLFormElement)) {
                return;
            }

            chatForm.requestSubmit();
        });
    }
});
