/* global wp */

import React, {useState} from 'react';
import {
    apiDeleteBot,
    apiDisconnectBotFromChat,
    apiFetchUpdates,
    apiPingBot,
    apiSaveBot,
    apiSetBotChatStatus
} from '../utils/api';

const getConnectionStatus = (botId, chatId, connections) => {
    const relation = connections.find((item) => item?.data?.from === botId && item?.data?.to === chatId);
    const status = relation?.data?.meta?.status?.[0];

    return status || 'pending';
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

    const copyAuthCommand = async () => {
        try {
            await navigator.clipboard.writeText(form.authCommand);
            setFeedback({
                type: 'success',
                message: wp.i18n.__( 'Authorization command copied.', 'cf7-vk' )
            });
        } catch (error) {
            setFeedback({
                type: 'warning',
                message: wp.i18n.__( 'Copy the authorization command manually.', 'cf7-vk' )
            });
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

        const nextStatus = currentStatus === 'muted' ? 'active' : 'muted';
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

    const statusClass = bot.lastStatus || 'unknown';
    const lastSyncAt = bot.lastSyncAt || wp.i18n.__( 'not synced', 'cf7-vk' );
    const longPollState = bot.longPollServer
        ? wp.i18n.__( 'ready', 'cf7-vk' )
        : wp.i18n.__( 'not ready', 'cf7-vk' );

    return (
        <article className="cf7vk-card">
            <form className="cf7vk-form" onSubmit={save}>
                <div className="cf7vk-card-row">
                    <h3>{bot.title?.rendered || wp.i18n.__( 'Untitled bot', 'cf7-vk' )}</h3>
                    <span className={`cf7vk-status ${statusClass}`}>{statusClass}</span>
                </div>

                <label>
                    <span>{wp.i18n.__( 'Title', 'cf7-vk' )}</span>
                    <input value={form.title} onChange={updateField('title')} />
                </label>

                <label>
                    <span>{wp.i18n.__( 'Group ID', 'cf7-vk' )}</span>
                    <input value={form.groupId} onChange={updateField('groupId')} />
                </label>

                <label>
                    <span>{wp.i18n.__( 'Access token', 'cf7-vk' )}</span>
                    <input value={form.accessToken} onChange={updateField('accessToken')} />
                </label>

                <label>
                    <span>{wp.i18n.__( 'API version', 'cf7-vk' )}</span>
                    <input value={form.apiVersion} onChange={updateField('apiVersion')} />
                </label>

                <label>
                    <span>{wp.i18n.__( 'Authorization command', 'cf7-vk' )}</span>
                    <input value={form.authCommand} onChange={updateField('authCommand')} />
                </label>

                <p className="cf7vk-hint">
                    {wp.i18n.__( 'The command is matched strictly after trim, without alias expansion.', 'cf7-vk' )}
                </p>

                {feedback ? (
                    <div className={`cf7vk-notice ${feedback.type}`}>
                        <strong>{feedback.message}</strong>
                        {feedback.communityName ? (
                            <div>
                                {wp.i18n.__( 'VK community:', 'cf7-vk' )} {feedback.communityName}
                            </div>
                        ) : null}
                        {feedback.longPollError ? (
                            <div>
                                {wp.i18n.__( 'Long Poll:', 'cf7-vk' )} {feedback.longPollError}
                            </div>
                        ) : null}
                    </div>
                ) : null}

                <dl className="cf7vk-meta">
                    <dt>{wp.i18n.__( 'Token constant', 'cf7-vk' )}</dt>
                    <dd><code>{bot.accessTokenConst}</code></dd>
                    <dt>{wp.i18n.__( 'Stored by constant', 'cf7-vk' )}</dt>
                    <dd>{bot.isAccessTokenDefinedByConst ? wp.i18n.__( 'yes', 'cf7-vk' ) : wp.i18n.__( 'no', 'cf7-vk' )}</dd>
                    <dt>{wp.i18n.__( 'Group constant', 'cf7-vk' )}</dt>
                    <dd><code>{bot.groupIdConst}</code></dd>
                    <dt>{wp.i18n.__( 'Group set by constant', 'cf7-vk' )}</dt>
                    <dd>{bot.isGroupIdDefinedByConst ? wp.i18n.__( 'yes', 'cf7-vk' ) : wp.i18n.__( 'no', 'cf7-vk' )}</dd>
                    <dt>{wp.i18n.__( 'Long Poll', 'cf7-vk' )}</dt>
                    <dd>{longPollState}</dd>
                    <dt>{wp.i18n.__( 'Last sync', 'cf7-vk' )}</dt>
                    <dd>{lastSyncAt}</dd>
                    <dt>{wp.i18n.__( 'Auth command', 'cf7-vk' )}</dt>
                    <dd className="cf7vk-copy-row">
                        <code>{form.authCommand}</code>
                        <button className="button button-small" type="button" onClick={copyAuthCommand}>
                            {wp.i18n.__( 'Copy', 'cf7-vk' )}
                        </button>
                    </dd>
                </dl>

                <div className="cf7vk-actions">
                    <button className="button button-primary" type="submit" disabled={saving}>
                        {wp.i18n.__( 'Save', 'cf7-vk' )}
                    </button>
                    <button className="button" type="button" onClick={ping} disabled={saving || pinging}>
                        {pinging ? wp.i18n.__( 'Checking...', 'cf7-vk' ) : wp.i18n.__( 'Check connection', 'cf7-vk' )}
                    </button>
                    <button className="button" type="button" onClick={fetchUpdates} disabled={saving || fetching}>
                        {fetching ? wp.i18n.__( 'Syncing...', 'cf7-vk' ) : wp.i18n.__( 'Fetch dialogs', 'cf7-vk' )}
                    </button>
                    <button className="button" type="button" onClick={remove} disabled={saving}>
                        {wp.i18n.__( 'Remove', 'cf7-vk' )}
                    </button>
                </div>

                <div>
                    <strong>{wp.i18n.__( 'Linked dialogs', 'cf7-vk' )}</strong>
                    <p className="cf7vk-hint">
                        {wp.i18n.__( 'Pending dialogs are discovered but do not receive notifications until activated.', 'cf7-vk' )}
                    </p>
                    <ul className="cf7vk-list">
                        {chatsForBot.length === 0 ? (
                            <li>{wp.i18n.__( 'No dialogs linked yet.', 'cf7-vk' )}</li>
                        ) : chatsForBot.map((chat) => {
                            const status = getConnectionStatus(bot.id, chat.id, bot2ChatConnections);
                            const title = chat.title?.rendered || chat.displayName || `#${chat.id}`;

                            return (
                                <li key={chat.id} className={`cf7vk-chat-status ${status}`}>
                                    <span>{title}</span>
                                    <span>{status}</span>
                                    <div className="cf7vk-actions">
                                        {status === 'pending' ? (
                                            <button
                                                className="button button-small"
                                                type="button"
                                                onClick={() => handleActivatePendingChat(chat.id)}
                                                disabled={saving}
                                            >
                                                {wp.i18n.__( 'Activate', 'cf7-vk' )}
                                            </button>
                                        ) : (
                                            <button
                                                className="button button-small"
                                                type="button"
                                                onClick={() => handleToggleChatStatus(chat.id, status)}
                                                disabled={saving}
                                            >
                                                {status === 'muted' ? wp.i18n.__( 'Unmute', 'cf7-vk' ) : wp.i18n.__( 'Mute', 'cf7-vk' )}
                                            </button>
                                        )}
                                        <button
                                            className="button button-small"
                                            type="button"
                                            onClick={() => disconnectChat(chat.id)}
                                            disabled={saving}
                                        >
                                            {wp.i18n.__( 'Remove', 'cf7-vk' )}
                                        </button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            </form>
        </article>
    );
};

export default Bot;
