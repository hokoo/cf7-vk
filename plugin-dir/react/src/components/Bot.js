/* global wp */

import React, {useState} from 'react';
import BotView from './BotView';
import {
    apiDeleteBot,
    apiDisconnectBotFromChat,
    apiFetchUpdates,
    apiPingBot,
    apiSaveBot,
    apiSetBotChatStatus
} from '../utils/api';

const getStatusClass = (status) => {
    if ('online' === status || 'offline' === status) {
        return status;
    }

    return 'unknown';
};

const Bot = ({bot, chats = [], bot2ChatConnections = [], onUpdated}) => {
    const [form, setForm] = useState({
        title: bot.title?.rendered || '',
        groupId: bot.groupId || '',
        accessToken: bot.accessToken || '',
        apiVersion: bot.apiVersion || '5.199',
        authCommand: bot.authCommand || 'start'
    });
    const [saving, setSaving] = useState(false);
    const [pinging, setPinging] = useState(false);
    const [fetching, setFetching] = useState(false);
    const [feedback, setFeedback] = useState(null);

    const updateField = (field) => (event) => {
        setForm((current) => ({...current, [field]: event.target.value}));
    };

    const save = async (event) => {
        event.preventDefault();
        setSaving(true);
        setFeedback(null);

        try {
            await apiSaveBot(bot.id, form);
            await onUpdated();
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!window.confirm(wp.i18n.__( 'Remove this bot connection?', 'cf7-vk' ))) {
            return;
        }

        setSaving(true);

        try {
            await apiDeleteBot(bot.id);
            await onUpdated();
        } finally {
            setSaving(false);
        }
    };

    const ping = async () => {
        setPinging(true);
        setFeedback(null);

        try {
            const result = await apiPingBot(bot.id);
            setFeedback({
                type: result.longPollReady ? 'success' : 'warning',
                message: result.longPollReady
                    ? wp.i18n.__( 'Connection verified.', 'cf7-vk' )
                    : wp.i18n.__( 'Connection verified, but Long Poll is not ready yet.', 'cf7-vk' ),
                communityName: result.communityName || '',
                longPollError: result.longPollError || ''
            });
        } catch (error) {
            setFeedback({
                type: 'error',
                message: error.message
            });
        } finally {
            setPinging(false);
            await onUpdated();
        }
    };

    const fetchUpdates = async () => {
        setFetching(true);
        setFeedback(null);

        try {
            const result = await apiFetchUpdates(bot.id);
            const discoveredChats = result.updates?.length || 0;

            setFeedback({
                type: discoveredChats > 0 ? 'success' : 'warning',
                message: discoveredChats > 0
                    ? wp.i18n.__( 'New VK dialogs were synchronized.', 'cf7-vk' )
                    : wp.i18n.__( 'No new dialogs matched the authorization command.', 'cf7-vk' )
            });
        } catch (error) {
            setFeedback({
                type: 'error',
                message: error.message
            });
        } finally {
            setFetching(false);
            await onUpdated();
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
            await apiSetBotChatStatus(relation.data.id, nextStatus);
            await onUpdated();
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
            await apiSetBotChatStatus(relation.data.id, 'active');
            await onUpdated();
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
            await onUpdated();
        } finally {
            setSaving(false);
        }
    };

    const statusClass = getStatusClass(bot.lastStatus);
    const lastSyncAt = bot.lastSyncAt || wp.i18n.__( 'not synced', 'cf7-vk' );
    const longPollState = bot.longPollServer
        ? wp.i18n.__( 'ready', 'cf7-vk' )
        : wp.i18n.__( 'not ready', 'cf7-vk' );

    return (
        <BotView
            bot={bot}
            form={form}
            saving={saving}
            pinging={pinging}
            fetching={fetching}
            feedback={feedback}
            statusClass={statusClass}
            longPollState={longPollState}
            lastSyncAt={lastSyncAt}
            chatsForBot={chatsForBot}
            bot2ChatConnections={bot2ChatConnections}
            updateField={updateField}
            save={save}
            ping={ping}
            fetchUpdates={fetchUpdates}
            remove={remove}
            handleToggleChatStatus={handleToggleChatStatus}
            handleActivatePendingChat={handleActivatePendingChat}
            disconnectChat={disconnectChat}
        />
    );
};

export default Bot;
