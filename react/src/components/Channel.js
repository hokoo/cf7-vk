/* global wp */

import React, {useMemo, useState} from 'react';
import ChannelView from './ChannelView';
import {
    apiConnectBotToChannel,
    apiConnectChatToChannel,
    apiConnectFormToChannel,
    apiDeleteChannel,
    apiDisconnectBotFromChannel,
    apiDisconnectChatFromChannel,
    apiDisconnectFormFromChannel,
    apiSaveChannel
} from '../utils/api';

const getBotTitle = (bot) => bot?.title?.rendered || bot?.title || `#${bot.id}`;
const getChatTitle = (chat) => chat?.title?.rendered || chat?.displayName || `#${chat.id}`;
const getFormTitle = (form) => form?.title?.rendered || form?.title || `#${form.id}`;
const getBotStatusClass = (bot) => ('online' === bot?.lastStatus || 'offline' === bot?.lastStatus) ? bot.lastStatus : 'unknown';

const Channel = ({
    channel,
    bots,
    chats,
    forms,
    bot2ChatConnections,
    chat2ChannelConnections,
    bot2ChannelConnections,
    form2ChannelConnections,
    onChannelSaved,
    onChannelRemoved,
    refreshBotChannelConnections,
    refreshChatChannelConnections,
    refreshFormChannelConnections
}) => {
    const [titleValue, setTitleValue] = useState(channel.title?.rendered || '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [showFormSelector, setShowFormSelector] = useState(false);

    const botConnection = useMemo(
        () => bot2ChannelConnections.find((item) => item?.data?.to === channel.id),
        [bot2ChannelConnections, channel.id]
    );

    const linkedFormIds = useMemo(
        () => form2ChannelConnections
            .filter((item) => item?.data?.to === channel.id)
            .map((item) => item.data.from),
        [form2ChannelConnections, channel.id]
    );

    const linkedChatIds = useMemo(
        () => chat2ChannelConnections
            .filter((item) => item?.data?.to === channel.id)
            .map((item) => item.data.from),
        [chat2ChannelConnections, channel.id]
    );

    const assignedBot = botConnection
        ? bots.find((bot) => bot.id === botConnection.data.from)
        : null;

    const botForChannel = assignedBot ? {
        id: assignedBot.id,
        title: getBotTitle(assignedBot),
        statusClass: getBotStatusClass(assignedBot)
    } : null;

    const botChatConnections = assignedBot
        ? bot2ChatConnections.filter((item) => item?.data?.from === assignedBot.id)
        : [];

    const availableForms = forms.filter((form) => !linkedFormIds.includes(form.id)).map((form) => ({
        id: form.id,
        title: getFormTitle(form)
    }));

    const formsForChannel = forms.filter((form) => linkedFormIds.includes(form.id)).map((form) => ({
        id: form.id,
        title: getFormTitle(form)
    }));

    const availableBots = bots
        .filter((bot) => !assignedBot || bot.id !== assignedBot.id)
        .map((bot) => ({
            id: bot.id,
            title: getBotTitle(bot)
        }));

    const renderedChats = chats
        .filter((chat) => botChatConnections.some((item) => item?.data?.to === chat.id))
        .map((chat) => {
            const relation = botChatConnections.find((item) => item?.data?.to === chat.id);
            const statusMeta = relation?.data?.meta?.status?.[0] || 'pending';
            const isLinkedToChannel = linkedChatIds.includes(chat.id);

            if ('pending' === statusMeta) {
                return null;
            }

            return {
                id: chat.id,
                title: getChatTitle(chat),
                status: 'muted' === statusMeta ? 'Muted' : (isLinkedToChannel ? 'Active' : 'Paused')
            };
        })
        .filter(Boolean);

    const renderChannelClasses = () => {
        let classes = '';

        if (assignedBot && 'online' === getBotStatusClass(assignedBot)) {
            classes += ' has-bot-online';
        }

        if (renderedChats.some((chat) => 'Active' === chat.status)) {
            classes += ' has-active-chats';
        }

        if (formsForChannel.length > 0) {
            classes += ' has-forms';
        }

        return classes;
    };

    const saveTitle = async () => {
        const nextTitle = titleValue.trim();

        if (!nextTitle || nextTitle === (channel.title?.rendered || '')) {
            return;
        }

        setSaving(true);
        setError(null);

        try {
            const savedChannel = await apiSaveChannel(channel.id, {title: nextTitle});
            onChannelSaved(savedChannel);
        } catch (err) {
            setError(wp.i18n.__( 'Failed to update title', 'cf7-vk' ));
        } finally {
            setSaving(false);
        }
    };

    const handleKeyDown = (event) => {
        if ('Enter' === event.key) {
            saveTitle();
        }
    };

    const handleBotSelect = async (selectedOption) => {
        setSaving(true);

        try {
            if (botConnection) {
                await apiDisconnectBotFromChannel(botConnection.data.id);
            }

            if (selectedOption?.value) {
                await apiConnectBotToChannel(selectedOption.value, channel.id);
            }

            await refreshBotChannelConnections();
        } finally {
            setSaving(false);
        }
    };

    const handleRemoveBot = async () => {
        if (!botConnection) {
            return;
        }

        setSaving(true);

        try {
            await apiDisconnectBotFromChannel(botConnection.data.id);
            await refreshBotChannelConnections();
        } finally {
            setSaving(false);
        }
    };

    const handleAddForm = () => setShowFormSelector((current) => !current);

    const handleFormSelect = async (selectedOption) => {
        if (!selectedOption?.value) {
            setShowFormSelector(false);
            return;
        }

        setSaving(true);

        try {
            await apiConnectFormToChannel(selectedOption.value, channel.id);
            await refreshFormChannelConnections();
        } finally {
            setSaving(false);
            setShowFormSelector(false);
        }
    };

    const handleRemoveForm = async (formId) => {
        const connection = form2ChannelConnections.find(
            (item) => item?.data?.from === formId && item?.data?.to === channel.id
        );

        if (!connection) {
            return;
        }

        setSaving(true);

        try {
            await apiDisconnectFormFromChannel(connection.data.id);
            await refreshFormChannelConnections();
        } finally {
            setSaving(false);
        }
    };

    const handleToggleChat = async (chatId, status) => {
        const connection = chat2ChannelConnections.find(
            (item) => item?.data?.from === chatId && item?.data?.to === channel.id
        );

        if ('Muted' === status) {
            return;
        }

        setSaving(true);

        try {
            if (connection) {
                await apiDisconnectChatFromChannel(connection.data.id);
            } else {
                await apiConnectChatToChannel(chatId, channel.id);
            }

            await refreshChatChannelConnections();
        } finally {
            setSaving(false);
        }
    };

    const deleteChannel = async () => {
        if (!window.confirm(wp.i18n.__( 'Remove this channel?', 'cf7-vk' ))) {
            return;
        }

        let removed = false;

        setSaving(true);

        try {
            await apiDeleteChannel(channel.id);
            removed = true;
            onChannelRemoved(channel.id);
        } finally {
            if (!removed) {
                setSaving(false);
            }
        }
    };

    const getToggleButtonLabel = (status) => {
        switch (status.toLowerCase()) {
            case 'active':
                return wp.i18n.__( 'Pause', 'cf7-vk' );
            case 'paused':
                return wp.i18n.__( 'Activate', 'cf7-vk' );
            case 'muted':
                return wp.i18n.__( 'Muted by bot', 'cf7-vk' );
            default:
                return '';
        }
    };

    return (
        <ChannelView
            channel={channel}
            titleValue={titleValue}
            saving={saving}
            error={error}
            handleTitleChange={(event) => setTitleValue(event.target.value)}
            handleKeyDown={handleKeyDown}
            saveTitle={saveTitle}
            botForChannel={botForChannel}
            renderedChats={renderedChats}
            formsForChannel={formsForChannel}
            availableForms={availableForms}
            showFormSelector={showFormSelector}
            handleAddForm={handleAddForm}
            handleFormSelect={handleFormSelect}
            handleRemoveForm={handleRemoveForm}
            availableBots={availableBots}
            handleBotSelect={handleBotSelect}
            handleRemoveBot={handleRemoveBot}
            handleToggleChat={handleToggleChat}
            deleteChannel={deleteChannel}
            getToggleButtonLabel={getToggleButtonLabel}
            renderChannelClasses={renderChannelClasses}
        />
    );
};

export default Channel;
