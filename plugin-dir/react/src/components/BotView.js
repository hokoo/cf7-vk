/* global wp */

import React from 'react';
import {copyWithTooltip} from '../utils/main';
import {getChatStatus, getToggleButtonLabel} from '../utils/chatStatus';

const BotView = ({
    bot,
    form,
    saving,
    pinging,
    fetching,
    feedback,
    statusClass,
    longPollState,
    lastSyncAt,
    chatsForBot = [],
    bot2ChatConnections = [],
    updateField,
    save,
    ping,
    fetchUpdates,
    remove,
    handleToggleChatStatus,
    handleActivatePendingChat,
    disconnectChat
}) => {
    const botTitle = form.title.trim() || wp.i18n.__( 'Untitled bot', 'cf7-vk' );
    const authCommand = form.authCommand.trim() || 'start';
    const tokenDisplay = bot.isAccessTokenDefinedByConst
        ? bot.accessTokenConst
        : (form.accessToken ? `***${form.accessToken.slice(-4)}` : wp.i18n.__( '[empty]', 'cf7-vk' ));

    return (
        <div className={`entity-container bot ${statusClass}`} id={`bot-${bot.id}`}>
            <form className={`entity-wrapper bot-wrapper ${saving ? 'saving' : ''}`} onSubmit={save}>
                <div className="frame bot-summary">
                    <div className="bot-title">
                        <div className={`bot-name ${statusClass}`}>{botTitle}</div>
                        <div
                            className="bot-command copyable"
                            onClick={(event) => copyWithTooltip(event.currentTarget, authCommand)}
                            title={wp.i18n.__( 'Click to copy the authorization command', 'cf7-vk' )}
                        >
                            {authCommand}
                        </div>
                    </div>

                    <div className="bot-token">
                        <div className={`show-token${bot.isAccessTokenDefinedByConst ? ' const' : ''}`}>
                            {wp.i18n.__( 'token', 'cf7-vk' )}: <span className="token-value">{tokenDisplay}</span>
                        </div>

                        {!bot.isAccessTokenDefinedByConst ? (
                            <div
                                className="php-const-hint copyable"
                                title={wp.i18n.__( 'Click to copy the PHP constant example', 'cf7-vk' )}
                                onClick={(event) => copyWithTooltip(
                                    event.currentTarget,
                                    `const ${bot.accessTokenConst} = 'your_token';`
                                )}
                            >
                                {wp.i18n.__( 'set by PHP const', 'cf7-vk' )}
                            </div>
                        ) : null}
                    </div>
                </div>

                {feedback ? (
                    <div className={`frame bot-feedback ${feedback.type}`}>
                        <strong>{feedback.message}</strong>
                        {feedback.communityName ? (
                            <div>{wp.i18n.__( 'VK community:', 'cf7-vk' )} {feedback.communityName}</div>
                        ) : null}
                        {feedback.longPollError ? (
                            <div>{wp.i18n.__( 'Long Poll:', 'cf7-vk' )} {feedback.longPollError}</div>
                        ) : null}
                    </div>
                ) : null}

                <div className="frame bot-fields">
                    <div className="bot-field-grid">
                        <label className="bot-field">
                            <span>{wp.i18n.__( 'Title', 'cf7-vk' )}</span>
                            <input value={form.title} onChange={updateField('title')} disabled={saving} />
                        </label>

                        <label className="bot-field">
                            <span>{wp.i18n.__( 'Group ID', 'cf7-vk' )}</span>
                            <input value={form.groupId} onChange={updateField('groupId')} disabled={saving} />
                        </label>

                        <label className="bot-field bot-field--wide">
                            <span>{wp.i18n.__( 'Access token', 'cf7-vk' )}</span>
                            <input value={form.accessToken} onChange={updateField('accessToken')} disabled={saving} />
                        </label>

                        <label className="bot-field">
                            <span>{wp.i18n.__( 'API version', 'cf7-vk' )}</span>
                            <input value={form.apiVersion} onChange={updateField('apiVersion')} disabled={saving} />
                        </label>

                        <label className="bot-field">
                            <span>{wp.i18n.__( 'Authorization command', 'cf7-vk' )}</span>
                            <input value={form.authCommand} onChange={updateField('authCommand')} disabled={saving} />
                        </label>
                    </div>

                    <small className="field-hint">
                        {wp.i18n.__( 'The command is matched strictly after trim, without alias expansion.', 'cf7-vk' )}
                    </small>
                </div>

                <div className="frame bot-details">
                    <div className="bot-detail">
                        <span className="label">{wp.i18n.__( 'Long Poll', 'cf7-vk' )}</span>
                        <span className="value">{longPollState}</span>
                    </div>
                    <div className="bot-detail">
                        <span className="label">{wp.i18n.__( 'Last sync', 'cf7-vk' )}</span>
                        <span className="value">{lastSyncAt}</span>
                    </div>
                    <div className="bot-detail">
                        <span className="label">{wp.i18n.__( 'Group constant', 'cf7-vk' )}</span>
                        <span className="value mono">{bot.groupIdConst}</span>
                    </div>
                    <div className="bot-detail">
                        <span className="label">{wp.i18n.__( 'Stored by constant', 'cf7-vk' )}</span>
                        <span className="value">{bot.isAccessTokenDefinedByConst ? wp.i18n.__( 'yes', 'cf7-vk' ) : wp.i18n.__( 'no', 'cf7-vk' )}</span>
                    </div>
                </div>

                <div className="frame chats-for-bot">
                    <h5>{wp.i18n.__( 'Linked dialogs', 'cf7-vk' )}</h5>

                    {chatsForBot.length > 0 ? (
                        <ul>
                            {chatsForBot.map((chat) => {
                                const status = getChatStatus(bot.id, chat.id, bot2ChatConnections);
                                const title = chat.title?.rendered || chat.displayName || `#${chat.id}`;

                                return (
                                    <li key={chat.id} className={`chat-item ${status.toLowerCase()}`}>
                                        <span className="chat-name" title={status}>{title}</span>
                                        <span
                                            className="action toggle-status"
                                            onClick={() => {
                                                if ('Pending' === status) {
                                                    handleActivatePendingChat(chat.id);
                                                    return;
                                                }

                                                handleToggleChatStatus(chat.id, status.toLowerCase());
                                            }}
                                        >
                                            {getToggleButtonLabel(status)}
                                        </span>
                                        <span
                                            className="action remove-chat"
                                            onClick={() => disconnectChat(chat.id)}
                                        >
                                            {wp.i18n.__( 'Remove', 'cf7-vk' )}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : 'offline' === statusClass ? (
                        <span className="offline-bot-sad-message">
                            {wp.i18n.__( 'Couldn’t load dialogs from VK right now.', 'cf7-vk' )}
                        </span>
                    ) : 'unknown' === statusClass ? (
                        <span className="unknown-bot-status-message">
                            {wp.i18n.__( 'Run a connection check to determine the transport state.', 'cf7-vk' )}
                        </span>
                    ) : (
                        <span className="no-chats-found">
                            {wp.i18n.__( 'Waiting for VK dialogs to join through the auth command...', 'cf7-vk' )}
                        </span>
                    )}
                </div>

                <div className="frame status-bar">
                    <div className="bot-actions">
                        <button className="action-button primary" type="submit" disabled={saving}>
                            {wp.i18n.__( 'Save', 'cf7-vk' )}
                        </button>
                        <button className="action-button" type="button" onClick={ping} disabled={saving || pinging}>
                            {pinging ? wp.i18n.__( 'Checking…', 'cf7-vk' ) : wp.i18n.__( 'Check connection', 'cf7-vk' )}
                        </button>
                        <button className="action-button" type="button" onClick={fetchUpdates} disabled={saving || fetching}>
                            {fetching ? wp.i18n.__( 'Syncing…', 'cf7-vk' ) : wp.i18n.__( 'Fetch dialogs', 'cf7-vk' )}
                        </button>
                        <button className="remove-bot-button" type="button" onClick={remove} disabled={saving}>
                            {wp.i18n.__( 'Remove bot', 'cf7-vk' )}
                        </button>
                    </div>
                    <div className={`bot-status ${statusClass}`}>{statusClass}</div>
                </div>
            </form>
        </div>
    );
};

export default BotView;
