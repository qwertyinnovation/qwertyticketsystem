import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
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

    const chatThread = document.querySelector('[data-project-chat-thread]');
    const chatMessageField = document.querySelector('[data-project-chat-message]');
    const chatForm = document.querySelector('[data-project-chat-form]');

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

        if (chatThread instanceof HTMLElement && chatForm instanceof HTMLFormElement && window.axios) {
            const projectId = Number(chatThread.dataset.projectId || 0);
            const broadcastChannelName = chatThread.dataset.broadcastChannel || (projectId > 0 ? `projects.${projectId}` : '');
            const presenceChannelName = chatThread.dataset.presenceChannel || (projectId > 0 ? `projects.presence.${projectId}` : '');
            const messageCreatedEvent = chatThread.dataset.messageCreatedEvent || '.project.message.created';
            const messageUpdatedEvent = chatThread.dataset.messageUpdatedEvent || '.project.message.updated';
            const messageDeletedEvent = chatThread.dataset.messageDeletedEvent || '.project.message.deleted';
            const currentUserId = Number(chatThread.dataset.currentUserId || 0);
            const currentUserName = chatThread.dataset.currentUserName || '';
            const currentUserRole = chatThread.dataset.currentUserRole || '';
            const showUrl = chatThread.dataset.showUrl || '';
            const readUrl = chatThread.dataset.readUrl || '';
            const updateTemplate = chatThread.dataset.updateTemplate || '';
            const deleteTemplate = chatThread.dataset.deleteTemplate || '';
            const roleLabels = JSON.parse(chatThread.dataset.roleLabels || '{}');
            const errorMessage = chatForm.querySelector('[data-project-chat-error]');
            const typingIndicator = chatForm.querySelector('[data-project-chat-typing-indicator]');
            const onlineCountLabel = document.querySelector('[data-project-chat-online-count]');
            const editingBanner = chatForm.querySelector('[data-project-chat-editing-banner]');
            const editingLabel = chatForm.querySelector('[data-project-chat-editing-label]');
            const editingCancelButton = chatForm.querySelector('[data-project-chat-editing-cancel]');
            const sendButton = chatForm.querySelector('button[type="submit"]');
            const sendButtonLabel = chatForm.querySelector('[data-project-chat-submit-label]');
            const sendButtonIcon = chatForm.querySelector('[data-project-chat-submit-icon]');
            const canManageAllMessages = chatThread.dataset.canManageAllMessages !== 'false';
            const manageAllRoles = ['admin', 'pm'];
            const participantNodes = new Map(
                Array.from(document.querySelectorAll('[data-project-chat-participant]'))
                    .map((node) => [Number(node.getAttribute('data-project-chat-participant') || 0), node])
            );
            const knownMessageIds = new Set(
                Array.from(chatThread.querySelectorAll('[id^="message-"]'))
                    .map((messageNode) => Number((messageNode.getAttribute('id') || '').replace('message-', '')))
                    .filter((messageId) => Number.isInteger(messageId) && messageId > 0)
            );
            const onlineUserIds = new Set();
            const typingUsers = new Map();
            const typingTimeouts = new Map();
            let presenceChannel = null;
            let isTyping = false;
            let stopTypingTimer = null;
            let editingMessageId = 0;

            const escapeHtml = (value) => String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const messageInitial = (name) => String(name || 'U').trim().charAt(0).toUpperCase() || 'U';
            const updateActionFor = (messageId) => updateTemplate.replace('__MESSAGE__', String(messageId));
            const deleteActionFor = (messageId) => deleteTemplate.replace('__MESSAGE__', String(messageId));
            const roleLabelFor = (role) => roleLabels[role || ''] || 'User';

            const renderTypingIndicator = () => {
                if (!(typingIndicator instanceof HTMLElement)) {
                    return;
                }

                const typingNames = Array.from(typingUsers.values());

                if (typingNames.length === 0) {
                    typingIndicator.textContent = '';

                    return;
                }

                if (typingNames.length === 1) {
                    typingIndicator.textContent = `${typingNames[0]} is typing...`;

                    return;
                }

                typingIndicator.textContent = `${typingNames.slice(0, 2).join(', ')} are typing...`;
            };

            const setParticipantPresence = (userId, isOnline) => {
                const participantNode = participantNodes.get(Number(userId));

                if (!(participantNode instanceof HTMLElement)) {
                    return;
                }

                const dot = participantNode.querySelector('[data-project-chat-presence-dot]');
                const label = participantNode.querySelector('[data-project-chat-presence-label]');

                if (dot instanceof HTMLElement) {
                    dot.classList.toggle('bg-emerald-500', isOnline);
                    dot.classList.toggle('shadow-[0_0_0_4px_rgba(16,185,129,0.15)]', isOnline);
                    dot.classList.toggle('bg-slate-300', !isOnline);
                }

                if (label instanceof HTMLElement) {
                    label.textContent = isOnline ? 'Online now' : 'Offline';
                    label.classList.toggle('text-emerald-600', isOnline);
                    label.classList.toggle('text-slate-500', !isOnline);
                }
            };

            const renderPresence = () => {
                participantNodes.forEach((_, userId) => {
                    setParticipantPresence(userId, onlineUserIds.has(userId));
                });

                if (onlineCountLabel instanceof HTMLElement) {
                    const onlineCount = onlineUserIds.size;
                    onlineCountLabel.textContent = `${onlineCount} ${onlineCount === 1 ? 'member is' : 'members are'} online`;
                }
            };

            const scheduleTypingReset = (userId) => {
                const existingTimeout = typingTimeouts.get(userId);

                if (existingTimeout) {
                    window.clearTimeout(existingTimeout);
                }

                typingTimeouts.set(
                    userId,
                    window.setTimeout(() => {
                        typingUsers.delete(userId);
                        typingTimeouts.delete(userId);
                        renderTypingIndicator();
                    }, 1800)
                );
            };

            const setRemoteTyping = (userId, userName, typing) => {
                if (Number(userId) === currentUserId) {
                    return;
                }

                if (!typing) {
                    const timeoutId = typingTimeouts.get(userId);

                    if (timeoutId) {
                        window.clearTimeout(timeoutId);
                        typingTimeouts.delete(userId);
                    }

                    typingUsers.delete(userId);
                    renderTypingIndicator();

                    return;
                }

                typingUsers.set(userId, userName || 'Someone');
                scheduleTypingReset(userId);
                renderTypingIndicator();
            };

            const whisperTyping = (typing) => {
                if (!presenceChannel) {
                    return;
                }

                presenceChannel.whisper('typing', {
                    user_id: currentUserId,
                    user_name: currentUserName,
                    typing,
                });
            };

            const stopTyping = () => {
                if (!isTyping) {
                    return;
                }

                isTyping = false;

                if (stopTypingTimer) {
                    window.clearTimeout(stopTypingTimer);
                    stopTypingTimer = null;
                }

                whisperTyping(false);
            };

            const queueStopTyping = () => {
                if (stopTypingTimer) {
                    window.clearTimeout(stopTypingTimer);
                }

                stopTypingTimer = window.setTimeout(() => {
                    stopTyping();
                }, 1200);
            };

            const handleLocalTyping = () => {
                if (!(chatMessageField instanceof HTMLTextAreaElement)) {
                    return;
                }

                if (chatMessageField.value.trim() === '') {
                    stopTyping();

                    return;
                }

                if (!isTyping) {
                    isTyping = true;
                    whisperTyping(true);
                }

                queueStopTyping();
            };

            const markRoomRead = async (messageId = 0) => {
                if (readUrl === '') {
                    return;
                }

                try {
                    await window.axios.post(
                        readUrl,
                        messageId > 0 ? { message_id: messageId } : {},
                        {
                            headers: {
                                Accept: 'application/json',
                            },
                        }
                    );
                } catch (error) {
                    // Ignore transient read-sync failures; they should not block the chat UI.
                }
            };

            const updateEmptyState = () => {
                const messageCount = chatThread.querySelectorAll('[id^="message-"]').length;

                if (messageCount > 0) {
                    chatThread.querySelector('.messenger-empty-state')?.remove();

                    return;
                }

                if (chatThread.querySelector('.messenger-empty-state')) {
                    return;
                }

                const emptyState = document.createElement('div');
                emptyState.className = 'messenger-empty-state';
                emptyState.innerHTML = `
                    <div class="messenger-empty-icon">
                        <i class="fa-solid fa-comments" aria-hidden="true"></i>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">No messages yet.</p>
                    <p class="mt-1 text-sm text-slate-500">Send the first update and this room becomes the project thread.</p>
                `;
                chatThread.appendChild(emptyState);
            };

            const clearComposerEditMode = ({ keepMessage = false } = {}) => {
                editingMessageId = 0;
                chatThread.querySelectorAll('.messenger-message.is-editing').forEach((messageNode) => {
                    messageNode.classList.remove('is-editing');
                });

                if (editingBanner instanceof HTMLElement) {
                    editingBanner.classList.add('hidden');
                    editingBanner.classList.remove('inline-flex');
                }

                if (editingLabel instanceof HTMLElement) {
                    editingLabel.textContent = 'Editing message';
                }

                if (sendButtonLabel instanceof HTMLElement) {
                    sendButtonLabel.textContent = 'Send';
                }

                if (sendButtonIcon instanceof HTMLElement) {
                    sendButtonIcon.className = 'fa-solid fa-paper-plane';
                }

                chatMessageField.placeholder = 'Write a message... Press Enter to send';
                setComposerError('');

                if (!keepMessage) {
                    chatMessageField.value = '';
                    syncComposerHeight();
                }
            };

            const startComposerEditMode = (messageNode) => {
                if (!(messageNode instanceof HTMLElement)) {
                    return;
                }

                const messageId = Number((messageNode.getAttribute('id') || '').replace('message-', ''));
                const bubbleText = messageNode.querySelector('.messenger-bubble p');
                const authorName = messageNode.querySelector('.messenger-message-meta span');
                const messageText = bubbleText?.textContent?.trim() || '';

                if (!Number.isInteger(messageId) || messageId < 1) {
                    return;
                }

                clearComposerEditMode({ keepMessage: true });
                editingMessageId = messageId;
                messageNode.classList.add('is-editing');
                chatMessageField.value = messageText;
                chatMessageField.placeholder = 'Edit message... Press Enter to save';
                syncComposerHeight();

                if (editingBanner instanceof HTMLElement) {
                    editingBanner.classList.remove('hidden');
                    editingBanner.classList.add('inline-flex');
                }

                if (editingLabel instanceof HTMLElement) {
                    editingLabel.textContent = `Editing ${authorName?.textContent?.trim() || 'message'}`;
                }

                if (sendButtonLabel instanceof HTMLElement) {
                    sendButtonLabel.textContent = 'Update';
                }

                if (sendButtonIcon instanceof HTMLElement) {
                    sendButtonIcon.className = 'fa-solid fa-pen-to-square';
                }

                setComposerError('');
                chatMessageField.focus();
                chatMessageField.setSelectionRange(chatMessageField.value.length, chatMessageField.value.length);
                messageNode.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            };

            const updateMessageNode = (message) => {
                const messageNode = chatThread.querySelector(`#message-${message.id}`);

                if (!(messageNode instanceof HTMLElement)) {
                    appendMessage(message);

                    return;
                }

                const bubbleText = messageNode.querySelector('.messenger-bubble p');
                const timestamp = messageNode.querySelector('.messenger-message-meta span:last-child');

                if (bubbleText instanceof HTMLElement) {
                    bubbleText.textContent = message.message || '';
                }

                if (timestamp instanceof HTMLElement) {
                    timestamp.textContent = message.created_at_label || '';
                }

                if (editingMessageId === Number(message.id || 0)) {
                    clearComposerEditMode();
                }
            };

            const removeMessageNode = (messageId) => {
                const messageNode = chatThread.querySelector(`#message-${messageId}`);

                if (messageNode instanceof HTMLElement) {
                    messageNode.remove();
                }

                if (editingMessageId === Number(messageId)) {
                    clearComposerEditMode();
                }

                knownMessageIds.delete(Number(messageId));
                updateEmptyState();
            };

            const appendMessage = (message) => {
                const messageId = Number(message.id || 0);

                if (!Number.isInteger(messageId) || messageId < 1 || knownMessageIds.has(messageId)) {
                    return;
                }

                knownMessageIds.add(messageId);

                const isCurrentUser = Number(message.user_id || 0) === currentUserId;
                const canManageMessage = isCurrentUser || (canManageAllMessages && manageAllRoles.includes(currentUserRole));
                const authorName = message.author_name || 'Unknown User';
                const authorRoleLabel = message.author_role_label || roleLabelFor(message.author_role);

                const article = document.createElement('article');
                article.id = `message-${messageId}`;
                article.className = `messenger-message ${isCurrentUser ? 'is-current-user' : 'is-remote-user'}`;

                article.innerHTML = `
                    ${isCurrentUser ? '' : `<div class="messenger-message-avatar">${escapeHtml(messageInitial(authorName))}</div>`}
                    <div class="messenger-bubble-cluster">
                        <div class="messenger-message-meta">
                            <span class="font-bold text-slate-800">${escapeHtml(authorName)}</span>
                            <span>${escapeHtml(authorRoleLabel)}</span>
                            <span>•</span>
                            <span>${escapeHtml(message.created_at_label || '')}</span>
                        </div>
                        <div class="messenger-bubble">
                            <p class="whitespace-pre-line text-sm leading-6">${escapeHtml(message.message || '')}</p>
                        </div>
                        ${canManageMessage ? `
                            <div class="messenger-message-actions">
                                <a href="${escapeHtml(`#message-${messageId}`)}" class="messenger-message-action" data-project-chat-edit>Edit</a>
                                <form method="POST" action="${escapeHtml(deleteActionFor(messageId))}" data-project-chat-delete-form>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="messenger-message-action messenger-message-action-danger" onclick="return confirm('Delete this message?');">Delete</button>
                                </form>
                            </div>
                        ` : ''}
                    </div>
                    ${isCurrentUser ? `<div class="messenger-message-avatar messenger-message-avatar-self">${escapeHtml(messageInitial(authorName))}</div>` : ''}
                `;

                chatThread.querySelector('.messenger-empty-state')?.remove();
                chatThread.appendChild(article);
                chatThread.scrollTop = chatThread.scrollHeight;
            };

            const setComposerError = (message) => {
                if (!(errorMessage instanceof HTMLElement)) {
                    return;
                }

                const hasMessage = message.trim() !== '';
                errorMessage.textContent = message;
                errorMessage.classList.toggle('hidden', !hasMessage);
            };

            chatThread.scrollTop = chatThread.scrollHeight;
            updateEmptyState();

            chatForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (chatMessageField.value.trim() === '') {
                    setComposerError('Message is required.');

                    return;
                }

                setComposerError('');
                stopTyping();
                chatMessageField.disabled = true;
                sendButton?.setAttribute('disabled', 'disabled');

                try {
                    if (editingMessageId > 0) {
                        const response = await window.axios.post(
                            updateActionFor(editingMessageId),
                            {
                                _method: 'PUT',
                                editing_message_id: editingMessageId,
                                update_message: chatMessageField.value,
                            },
                            {
                                headers: {
                                    Accept: 'application/json',
                                },
                            }
                        );

                        updateMessageNode(response.data.message || {});
                    } else {
                        const response = await window.axios.post(
                            chatForm.action,
                            { message: chatMessageField.value },
                            {
                                headers: {
                                    Accept: 'application/json',
                                },
                            }
                        );

                        appendMessage(response.data.message || {});
                    }

                    clearComposerEditMode();
                    stopTyping();
                    chatMessageField.focus();
                } catch (error) {
                    const validationMessage = editingMessageId > 0
                        ? error?.response?.data?.errors?.update_message?.[0]
                        : error?.response?.data?.errors?.message?.[0];
                    setComposerError(validationMessage || 'Could not save message. Please try again.');
                } finally {
                    chatMessageField.disabled = false;
                    sendButton?.removeAttribute('disabled');
                }
            });

            chatThread.addEventListener('click', (event) => {
                const editLink = event.target.closest('[data-project-chat-edit]');

                if (editLink instanceof HTMLElement) {
                    event.preventDefault();
                    const messageNode = editLink.closest('[id^="message-"]');
                    startComposerEditMode(messageNode);

                    return;
                }
            });

            chatThread.addEventListener('submit', async (event) => {
                const deleteForm = event.target.closest('[data-project-chat-delete-form]');

                if (deleteForm instanceof HTMLFormElement) {
                    event.preventDefault();

                    if (!window.confirm('Delete this message?')) {
                        return;
                    }

                    const submitButton = deleteForm.querySelector('button[type="submit"]');
                    const messageNode = deleteForm.closest('[id^="message-"]');
                    const messageId = Number((messageNode?.getAttribute('id') || '').replace('message-', ''));

                    submitButton?.setAttribute('disabled', 'disabled');

                    try {
                        await window.axios.post(
                            deleteForm.action,
                            { _method: 'DELETE' },
                            {
                                headers: {
                                    Accept: 'application/json',
                                },
                            }
                        );

                        removeMessageNode(messageId);
                    } catch (error) {
                        window.alert('Could not delete message. Please try again.');
                        submitButton?.removeAttribute('disabled');
                    }
                }
            });

            chatMessageField.addEventListener('input', handleLocalTyping);
            chatMessageField.addEventListener('blur', () => {
                stopTyping();
            });
            editingCancelButton?.addEventListener('click', () => {
                clearComposerEditMode();
                chatMessageField.focus();
            });

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    markRoomRead();
                }
            });

            window.addEventListener('beforeunload', () => {
                stopTyping();
            });

            clearComposerEditMode({ keepMessage: true });
            markRoomRead();

            if (broadcastChannelName !== '' && window.Echo) {
                window.Echo.private(broadcastChannelName)
                    .listen(messageCreatedEvent, (message) => {
                        appendMessage(message);

                        if (document.visibilityState === 'visible') {
                            markRoomRead(Number(message.id || 0));
                        }
                    })
                    .listen(messageUpdatedEvent, (message) => {
                        updateMessageNode(message);
                    })
                    .listen(messageDeletedEvent, (payload) => {
                        removeMessageNode(payload.id);
                    });
            }

            if (presenceChannelName !== '' && window.Echo) {
                presenceChannel = window.Echo.join(presenceChannelName)
                    .here((users) => {
                        onlineUserIds.clear();
                        users.forEach((user) => {
                            onlineUserIds.add(Number(user.id || 0));
                        });
                        renderPresence();
                    })
                    .joining((user) => {
                        onlineUserIds.add(Number(user.id || 0));
                        renderPresence();
                    })
                    .leaving((user) => {
                        const userId = Number(user.id || 0);
                        onlineUserIds.delete(userId);
                        setRemoteTyping(userId, user.name || 'Someone', false);
                        renderPresence();
                    })
                    .listenForWhisper('typing', (payload) => {
                        setRemoteTyping(
                            Number(payload?.user_id || 0),
                            payload?.user_name || 'Someone',
                            Boolean(payload?.typing)
                        );
                    });
            }
        }
    }
});
