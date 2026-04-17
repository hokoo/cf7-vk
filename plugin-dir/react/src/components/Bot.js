/* global wp, cf7VkData */

import React, {useEffect, useRef, useState} from 'react';
import BotView from './BotView';
import {
    apiActivateBotChat,
    apiDeleteBot,
    apiDisconnectBotFromChat,
    apiFetchUpdates,
    apiPingBot,
    apiSaveBot,
    apiSetBotChatStatus
} from '../utils/api';

const SAVE_DEBOUNCE_MS = 700;
const RETRY_DELAY_MS = 5000;
const POLL_CONTINUE_DELAY_MS = 250;

const getStatusClass = (status) => {
    if ('online' === status || 'offline' === status) {
        return status;
    }

    return 'unknown';
};

const getEmptySecret = () => cf7VkData?.phrases?.emptySecret || wp.i18n.__( '[empty]', 'message-bridge-for-contact-form-7-and-vk' );

const getEditableAccessToken = (bot) => {
    if (bot.isAccessTokenDefinedByConst || bot.isAccessTokenEmpty || bot.accessToken === getEmptySecret()) {
        return '';
    }

    return bot.accessToken || '';
};

const buildFormState = (bot) => ({
    groupId: bot.groupId || '',
    accessToken: getEditableAccessToken(bot),
    authCommand: bot.authCommand || 'start'
});

const cloneForm = (form) => ({
    groupId: form.groupId,
    accessToken: form.accessToken,
    authCommand: form.authCommand
});

const serializeForm = (form) => JSON.stringify(cloneForm(form));

const hasConfiguredConnection = (bot, form = null) => {
    const groupId = (form?.groupId ?? bot.groupId ?? '').trim();
    const providedToken = 'string' === typeof form?.accessToken
        ? form.accessToken.trim()
        : null;
    const hasAccessToken = null === providedToken
        ? Boolean(bot.isAccessTokenDefinedByConst || !bot.isAccessTokenEmpty)
        : Boolean(providedToken || bot.isAccessTokenDefinedByConst);

    return '' !== groupId && hasAccessToken;
};

const didConnectionSettingsChange = (previousForm, nextForm) => (
    previousForm.groupId !== nextForm.groupId ||
    previousForm.accessToken !== nextForm.accessToken
);

const applyRuntimeBotTitle = (bot, nextTitle) => {
    if (!nextTitle) {
        return bot;
    }

    return {
        ...bot,
        title: {
            ...(bot.title || {}),
            rendered: nextTitle
        }
    };
};

const Bot = ({
    bot,
    chats = [],
    bot2ChatConnections = [],
    onBotSaved,
    onBotRemoved,
    refreshBots,
    refreshBotRuntime,
    refreshBotChatConnections,
    refreshChatChannelConnections
}) => {
    const persistedGroupId = bot.groupId || '';
    const persistedAccessToken = getEditableAccessToken(bot);
    const persistedAuthCommand = bot.authCommand || 'start';
    const hasPersistedConnection = hasConfiguredConnection({
        groupId: persistedGroupId,
        isAccessTokenDefinedByConst: bot.isAccessTokenDefinedByConst,
        isAccessTokenEmpty: bot.isAccessTokenEmpty
    });

    const [form, setForm] = useState(() => buildFormState(bot));
    const [saving, setSaving] = useState(false);
    const [, setPinging] = useState(false);
    const [, setFetching] = useState(false);
    const [feedback, setFeedback] = useState(null);
    const [pollingEnabled, setPollingEnabled] = useState(false);
    const [isEditingToken, setIsEditingToken] = useState(false);
    const [isEditingCommand, setIsEditingCommand] = useState(false);

    const formRef = useRef(form);
    const saveTimeoutRef = useRef(null);
    const pollTimeoutRef = useRef(null);
    const isSavingRef = useRef(false);
    const pollInFlightRef = useRef(false);
    const previousBotIdRef = useRef(bot.id);
    const lastSavedFormRef = useRef(buildFormState(bot));
    const lastSavedSnapshotRef = useRef(serializeForm(lastSavedFormRef.current));
    const flushSaveRef = useRef(null);
    const refreshBotsRef = useRef(refreshBots);
    const refreshBotRuntimeRef = useRef(refreshBotRuntime);
    const initialPingBotIdRef = useRef(null);
    const runPingRef = useRef(null);

    useEffect(() => {
        formRef.current = form;
    }, [form]);

    useEffect(() => {
        refreshBotsRef.current = refreshBots;
        refreshBotRuntimeRef.current = refreshBotRuntime;
    }, [refreshBots, refreshBotRuntime]);

    useEffect(() => {
        const nextSavedForm = {
            groupId: persistedGroupId,
            accessToken: persistedAccessToken,
            authCommand: persistedAuthCommand
        };
        const nextSnapshot = serializeForm(nextSavedForm);
        const currentSnapshot = serializeForm(formRef.current);
        const botChanged = previousBotIdRef.current !== bot.id;
        const hasUnsavedChanges = currentSnapshot !== lastSavedSnapshotRef.current;

        previousBotIdRef.current = bot.id;

        if (botChanged || (!hasUnsavedChanges && currentSnapshot !== nextSnapshot)) {
            lastSavedFormRef.current = nextSavedForm;
            lastSavedSnapshotRef.current = nextSnapshot;
            formRef.current = nextSavedForm;
            setForm(nextSavedForm);
        }

        if (!hasPersistedConnection) {
            setPollingEnabled(false);
            initialPingBotIdRef.current = null;
        }
    }, [
        bot.id,
        persistedGroupId,
        persistedAccessToken,
        persistedAuthCommand,
        hasPersistedConnection,
        bot.isAccessTokenDefinedByConst,
        bot.isAccessTokenEmpty
    ]);

    useEffect(() => {
        if (isEditingToken || isEditingCommand) {
            return undefined;
        }

        if (serializeForm(form) === lastSavedSnapshotRef.current) {
            return undefined;
        }

        window.clearTimeout(saveTimeoutRef.current);
        saveTimeoutRef.current = window.setTimeout(() => {
            flushSaveRef.current?.();
        }, SAVE_DEBOUNCE_MS);

        return () => {
            window.clearTimeout(saveTimeoutRef.current);
        };
    }, [form, isEditingToken, isEditingCommand]);

    useEffect(() => {
        if (!hasPersistedConnection) {
            return undefined;
        }

        if (initialPingBotIdRef.current === bot.id) {
            return undefined;
        }

        initialPingBotIdRef.current = bot.id;
        runPingRef.current?.({showError: false});

        return undefined;
    }, [bot.id, hasPersistedConnection]);

    useEffect(() => {
        if (!pollingEnabled || !hasPersistedConnection) {
            window.clearTimeout(pollTimeoutRef.current);
            pollTimeoutRef.current = null;
            return undefined;
        }

        let cancelled = false;

        const scheduleNextPoll = (delay = 0) => {
            window.clearTimeout(pollTimeoutRef.current);
            pollTimeoutRef.current = window.setTimeout(runPoll, delay);
        };

        const runPoll = async () => {
            if (cancelled) {
                return;
            }

            if (pollInFlightRef.current) {
                scheduleNextPoll(RETRY_DELAY_MS);
                return;
            }

            pollInFlightRef.current = true;
            setFetching(true);

            try {
                const result = await apiFetchUpdates(bot.id);
                const hasLinkedDialogChanges = Boolean(result?.hasNewChats || result?.hasNewConnections);
                const shouldRefreshBotState = Boolean(result?.failed);
                const wasSkippedByLock = Boolean(result?.locked);

                if (cancelled) {
                    return;
                }

                if (wasSkippedByLock) {
                    scheduleNextPoll(RETRY_DELAY_MS);
                    return;
                }

                if (hasLinkedDialogChanges) {
                    await refreshBotRuntimeRef.current();
                } else if (shouldRefreshBotState) {
                    await refreshBotsRef.current();
                }

                if (!cancelled) {
                    scheduleNextPoll(shouldRefreshBotState ? RETRY_DELAY_MS : POLL_CONTINUE_DELAY_MS);
                }
            } catch (error) {
                if (cancelled) {
                    return;
                }

                setFeedback({
                    type: 'error',
                    message: error.message
                });
                await refreshBotsRef.current();

                if (400 === error.status) {
                    setPollingEnabled(false);
                    return;
                }

                scheduleNextPoll(RETRY_DELAY_MS);
            } finally {
                pollInFlightRef.current = false;
                if (!cancelled) {
                    setFetching(false);
                }
            }
        };

        scheduleNextPoll();

        return () => {
            cancelled = true;
            window.clearTimeout(pollTimeoutRef.current);
            pollTimeoutRef.current = null;
        };
    }, [
        bot.id,
        persistedGroupId,
        persistedAccessToken,
        hasPersistedConnection,
        bot.isAccessTokenDefinedByConst,
        bot.isAccessTokenEmpty,
        pollingEnabled
    ]);

    const scheduleImmediateSave = () => {
        window.clearTimeout(saveTimeoutRef.current);
        saveTimeoutRef.current = window.setTimeout(() => {
            flushSave();
        }, 0);
    };

    const runPing = async ({showError = true} = {}) => {
        setPinging(true);
        if (showError) {
            setFeedback(null);
        }

        try {
            const result = await apiPingBot(bot.id);
            const runtimeBotTitle = result.communityName || result.botName || '';

            if (runtimeBotTitle) {
                onBotSaved(applyRuntimeBotTitle(bot, runtimeBotTitle));
            }

            await refreshBots();
            setPollingEnabled(Boolean(result.longPollReady));

            return result;
        } catch (error) {
            if (showError) {
                setFeedback({
                    type: 'error',
                    message: error.message
                });
            }
            setPollingEnabled(false);
            await refreshBots();

            return null;
        } finally {
            setPinging(false);
        }
    };

    runPingRef.current = runPing;

    const flushSave = async () => {
        window.clearTimeout(saveTimeoutRef.current);

        const nextForm = cloneForm(formRef.current);
        const nextSnapshot = serializeForm(nextForm);

        if (nextSnapshot === lastSavedSnapshotRef.current) {
            return;
        }

        if (isSavingRef.current) {
            scheduleImmediateSave();
            return;
        }

        const previousForm = cloneForm(lastSavedFormRef.current);
        const connectionSettingsChanged = didConnectionSettingsChange(previousForm, nextForm);

        isSavingRef.current = true;
        setSaving(true);

        if (connectionSettingsChanged) {
            setPollingEnabled(false);
        }

        try {
            const savedBot = await apiSaveBot(bot.id, nextForm);

            lastSavedFormRef.current = nextForm;
            lastSavedSnapshotRef.current = nextSnapshot;
            onBotSaved(savedBot);

            if (connectionSettingsChanged) {
                if (hasConfiguredConnection(savedBot, nextForm)) {
                    await runPing();
                } else {
                    setFeedback(null);
                    await refreshBots();
                }
            }
        } catch (error) {
            setFeedback({
                type: 'error',
                message: error.message
            });
        } finally {
            isSavingRef.current = false;
            setSaving(false);

            if (serializeForm(formRef.current) !== lastSavedSnapshotRef.current) {
                scheduleImmediateSave();
            }
        }
    };

    flushSaveRef.current = flushSave;

    const updateField = (field) => (event) => {
        setFeedback(null);
        setForm((current) => ({...current, [field]: event.target.value}));
    };

    const commitInlineEdit = (field) => {
        if ('accessToken' === field) {
            setIsEditingToken(false);
        }

        if ('authCommand' === field) {
            setIsEditingCommand(false);
        }

        scheduleImmediateSave();
    };

    const cancelInlineEdit = (field) => {
        const previousValue = lastSavedFormRef.current[field] || '';

        if ('accessToken' === field) {
            setIsEditingToken(false);
        }

        if ('authCommand' === field) {
            setIsEditingCommand(false);
        }

        setForm((current) => ({...current, [field]: previousValue}));
        setFeedback(null);
    };

    const handleInlineEditorKeyDown = (field) => (event) => {
        if ('Enter' === event.key) {
            event.preventDefault();
            commitInlineEdit(field);
        }

        if ('Escape' === event.key) {
            event.preventDefault();
            cancelInlineEdit(field);
        }
    };

    const startEditingToken = () => {
        if (bot.isAccessTokenDefinedByConst || isEditingCommand) {
            return;
        }

        setFeedback(null);
        setIsEditingToken(true);
    };

    const startEditingCommand = () => {
        if (isEditingToken) {
            return;
        }

        setFeedback(null);
        setIsEditingCommand(true);
    };

    const remove = async () => {
        if (!window.confirm(wp.i18n.__( 'Remove this bot connection?', 'message-bridge-for-contact-form-7-and-vk' ))) {
            return;
        }

        let removed = false;

        window.clearTimeout(saveTimeoutRef.current);
        window.clearTimeout(pollTimeoutRef.current);
        setPollingEnabled(false);
        setSaving(true);

        try {
            await apiDeleteBot(bot.id);
            removed = true;
            onBotRemoved(bot.id);
        } finally {
            if (!removed) {
                setSaving(false);
            }
        }
    };

    const relatedChatIds = bot2ChatConnections
        .filter((item) => item?.data?.from === bot.id)
        .map((item) => item.data.to);

    const chatsForBot = chats.filter((chat) => relatedChatIds.includes(chat.id));

    const handleToggleChatStatus = async (chatId, currentStatus) => {
        const relation = bot2ChatConnections.find((item) => item?.data?.from === bot.id && item?.data?.to === chatId);

        if (!relation) {
            return;
        }

        const nextStatus = 'muted' === currentStatus ? 'active' : 'muted';
        setSaving(true);

        try {
            if ('active' === nextStatus) {
                await apiActivateBotChat(bot.id, chatId);
                await Promise.all([
                    refreshBotChatConnections(),
                    refreshChatChannelConnections()
                ]);
            } else {
                await apiSetBotChatStatus(relation.data.id, nextStatus);
                await refreshBotChatConnections();
            }
        } finally {
            setSaving(false);
        }
    };

    const handleActivatePendingChat = async (chatId) => {
        const relation = bot2ChatConnections.find((item) => item?.data?.from === bot.id && item?.data?.to === chatId);

        if (!relation) {
            return;
        }

        setSaving(true);

        try {
            await apiActivateBotChat(bot.id, chatId);
            await Promise.all([
                refreshBotChatConnections(),
                refreshChatChannelConnections()
            ]);
        } finally {
            setSaving(false);
        }
    };

    const disconnectChat = async (chatId) => {
        const relation = bot2ChatConnections.find((item) => item?.data?.from === bot.id && item?.data?.to === chatId);

        if (!relation) {
            return;
        }

        setSaving(true);

        try {
            await apiDisconnectBotFromChat(relation.data.id);
            await Promise.all([
                refreshBotChatConnections(),
                refreshChatChannelConnections()
            ]);
        } finally {
            setSaving(false);
        }
    };

    const statusClass = getStatusClass(bot.lastStatus);
    const hasConfiguredBot = hasConfiguredConnection(bot, form);

    return (
        <BotView
            bot={bot}
            form={form}
            saving={saving}
            feedback={feedback}
            statusClass={statusClass}
            chatsForBot={chatsForBot}
            bot2ChatConnections={bot2ChatConnections}
            updateField={updateField}
            remove={remove}
            handleFieldBlur={scheduleImmediateSave}
            handleToggleChatStatus={handleToggleChatStatus}
            handleActivatePendingChat={handleActivatePendingChat}
            disconnectChat={disconnectChat}
            hasConfiguredBot={hasConfiguredBot}
            isEditingToken={isEditingToken}
            isEditingCommand={isEditingCommand}
            startEditingToken={startEditingToken}
            startEditingCommand={startEditingCommand}
            commitInlineEdit={commitInlineEdit}
            cancelInlineEdit={cancelInlineEdit}
            handleInlineEditorKeyDown={handleInlineEditorKeyDown}
        />
    );
};

export default Bot;
