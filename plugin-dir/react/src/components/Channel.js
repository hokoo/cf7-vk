/* global wp */

import React, {useMemo, useState} from 'react';
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

const Channel = ({
    channel,
    bots,
    chats,
    forms,
    bot2ChatConnections,
    chat2ChannelConnections,
    bot2ChannelConnections,
    form2ChannelConnections,
    onUpdated
}) => {
    const [title, setTitle] = useState(channel.title?.rendered || '');
    const [saving, setSaving] = useState(false);

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

    const assignedBot = botConnection
        ? bots.find((bot) => bot.id === botConnection.data.from)
        : null;

    const assignedForms = forms.filter((form) => linkedFormIds.includes(form.id));
    const botChatConnections = assignedBot
        ? bot2ChatConnections.filter((item) => item?.data?.from === assignedBot.id)
        : [];
    const availableChats = chats.filter((chat) => botChatConnections.some((item) => item?.data?.to === chat.id));
    const linkedChatIds = chat2ChannelConnections
        .filter((item) => item?.data?.to === channel.id)
        .map((item) => item.data.from);
    const getFormTitle = (form) => form?.title?.rendered || form?.title || `#${form.id}`;
    const getBotTitle = (bot) => bot?.title?.rendered || bot?.title || `#${bot.id}`;
    const getChatTitle = (chat) => chat?.title?.rendered || chat?.displayName || `#${chat.id}`;

    const saveTitle = async (event) => {
        event.preventDefault();
        const nextTitle = title.trim();

        if (!nextTitle) {
            return;
        }

        setSaving(true);

        try {
            await apiSaveChannel(channel.id, {title: nextTitle});
            await onUpdated();
        } finally {
            setSaving(false);
        }
    };

    const onBotChange = async (event) => {
        const nextBotId = event.target.value;
        setSaving(true);

        try {
            if (botConnection) {
                await apiDisconnectBotFromChannel(botConnection.data.id);
            }

            if (nextBotId) {
                await apiConnectBotToChannel(parseInt(nextBotId, 10), channel.id);
            }

            await onUpdated();
        } finally {
            setSaving(false);
        }
    };

    const toggleForm = async (formId, isLinked) => {
        setSaving(true);

        try {
            const connection = form2ChannelConnections.find(
                (item) => item?.data?.from === formId && item?.data?.to === channel.id
            );

            if (isLinked && connection) {
                await apiDisconnectFormFromChannel(connection.data.id);
            } else if (!isLinked) {
                await apiConnectFormToChannel(formId, channel.id);
            }

            await onUpdated();
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!window.confirm(wp.i18n.__( 'Remove this channel?', 'cf7-vk' ))) {
            return;
        }

        setSaving(true);

        try {
            await apiDeleteChannel(channel.id);
            await onUpdated();
        } finally {
            setSaving(false);
        }
    };

    const toggleChat = async (chatId, isLinked) => {
        setSaving(true);

        try {
            const connection = chat2ChannelConnections.find(
                (item) => item?.data?.from === chatId && item?.data?.to === channel.id
            );

            if (isLinked && connection) {
                await apiDisconnectChatFromChannel(connection.data.id);
            } else if (!isLinked) {
                await apiConnectChatToChannel(chatId, channel.id);
            }

            await onUpdated();
        } finally {
            setSaving(false);
        }
    };

    return (
        <article className="cf7vk-card">
            <form className="cf7vk-form" onSubmit={saveTitle}>
                <label>
                    <span>{wp.i18n.__( 'Channel title', 'cf7-vk' )}</span>
                    <input value={title} onChange={(event) => setTitle(event.target.value)} disabled={saving} />
                </label>

                <div className="cf7vk-actions">
                    <button className="button button-primary" type="submit" disabled={saving || !title.trim()}>
                        {wp.i18n.__( 'Save channel', 'cf7-vk' )}
                    </button>
                </div>
            </form>

            <label className="cf7vk-form">
                <span>{wp.i18n.__( 'Assigned bot', 'cf7-vk' )}</span>
                <select value={assignedBot?.id || ''} onChange={onBotChange} disabled={saving}>
                    <option value="">{wp.i18n.__( 'No bot assigned', 'cf7-vk' )}</option>
                    {bots.map((bot) => (
                        <option key={bot.id} value={bot.id}>
                            {getBotTitle(bot)}
                        </option>
                    ))}
                </select>
            </label>

            <div>
                <strong>{wp.i18n.__( 'Connected forms', 'cf7-vk' )}</strong>
                <ul className="cf7vk-list">
                    {forms.map((form) => {
                        const isLinked = linkedFormIds.includes(form.id);
                        return (
                            <li key={form.id}>
                                <label>
                                    <input
                                        type="checkbox"
                                        checked={isLinked}
                                        onChange={() => toggleForm(form.id, isLinked)}
                                        disabled={saving}
                                    />
                                    {' '}
                                    {getFormTitle(form)}
                                </label>
                            </li>
                        );
                    })}
                </ul>
            </div>

            <div>
                <strong>{wp.i18n.__( 'Dialogs in this channel', 'cf7-vk' )}</strong>
                <ul className="cf7vk-list">
                    {availableChats.length === 0 ? (
                        <li>{wp.i18n.__( 'No linked dialogs are available for this bot yet.', 'cf7-vk' )}</li>
                    ) : availableChats.map((chat) => {
                        const isLinked = linkedChatIds.includes(chat.id);
                        const botChatConnection = botChatConnections.find((item) => item?.data?.to === chat.id);
                        const status = botChatConnection?.data?.meta?.status?.[0] || 'pending';

                        return (
                            <li key={chat.id}>
                                <label>
                                    <input
                                        type="checkbox"
                                        checked={isLinked}
                                        onChange={() => toggleChat(chat.id, isLinked)}
                                        disabled={saving || (!isLinked && status !== 'active')}
                                    />
                                    {' '}
                                    {getChatTitle(chat)} <span className={`cf7vk-inline-status ${status}`}>{status}</span>
                                </label>
                            </li>
                        );
                    })}
                </ul>
                <p className="cf7vk-hint">
                    {wp.i18n.__( 'Only active dialogs can receive form notifications and be attached to a channel.', 'cf7-vk' )}
                </p>
            </div>

            <div>
                <strong>{wp.i18n.__( 'Summary', 'cf7-vk' )}</strong>
                <ul className="cf7vk-list">
                    <li>
                        {wp.i18n.__( 'Bot:', 'cf7-vk' )}{' '}
                        {assignedBot ? getBotTitle(assignedBot) : wp.i18n.__( 'not assigned', 'cf7-vk' )}
                    </li>
                    <li>
                        {wp.i18n.__( 'Forms:', 'cf7-vk' )} {assignedForms.length}
                    </li>
                </ul>
            </div>

            <div className="cf7vk-actions">
                <button className="button" type="button" onClick={remove} disabled={saving}>
                    {wp.i18n.__( 'Remove channel', 'cf7-vk' )}
                </button>
            </div>
        </article>
    );
};

export default Channel;
