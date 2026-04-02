/* global wp, cf7VkData */

import React from 'react';
import {copyWithTooltip} from '../utils/main';
import {getChatStatus, getToggleButtonLabel} from '../utils/chatStatus';
import {formatBotTitle} from '../utils/botTitle';

const BotView = ({
    bot,
    form,
    saving,
    feedback,
    statusClass,
    chatsForBot = [],
    bot2ChatConnections = [],
    updateField,
    remove,
    handleFieldBlur,
    handleToggleChatStatus,
    handleActivatePendingChat,
    disconnectChat,
    hasConfiguredBot,
    isEditingToken,
    isEditingCommand,
    startEditingToken,
    startEditingCommand,
    commitInlineEdit,
    handleInlineEditorKeyDown
}) => {
    const botTitle = bot.title?.rendered || wp.i18n.__( 'Untitled bot', 'cf7-vk' );
    const visibleBotTitle = formatBotTitle(botTitle);
    const authCommand = form.authCommand.trim() || 'start';
    const emptySecret = cf7VkData?.phrases?.emptySecret || wp.i18n.__( '[empty]', 'cf7-vk' );
    const hasTokenValue = Boolean(form.accessToken.trim()) && form.accessToken !== emptySecret;
    const tokenDisplay = bot.isAccessTokenDefinedByConst
        ? bot.accessTokenConst
        : (hasTokenValue ? `***${form.accessToken.slice(-4)}` : emptySecret);

    return (
        <div className={`entity-container bot ${statusClass}`} id={`bot-${bot.id}`}>
            <div className={`entity-wrapper bot-wrapper ${saving ? 'saving' : ''}`}>
                <div className="frame bot-summary">
                    <div className="bot-title">
                        <div className={`bot-name ${statusClass}`} title={botTitle}>{visibleBotTitle}</div>

                        <div className={`bot-command-shell${isEditingCommand ? ' editing' : ''}`}>
                            {!isEditingCommand ? (
                                <>
                                    <div className="bot-command">{authCommand}</div>
                                    <div className="bot-command-actions">
                                        <button
                                            className="command-action left"
                                            type="button"
                                            onClick={startEditingCommand}
                                            disabled={saving}
                                        >
                                            {wp.i18n.__( 'Edit', 'cf7-vk' )}
                                        </button>
                                        <button
                                            className="command-action right copyable"
                                            type="button"
                                            onClick={(event) => copyWithTooltip(event.currentTarget, authCommand)}
                                            title={wp.i18n.__( 'Copy authorization command', 'cf7-vk' )}
                                            disabled={saving}
                                        >
                                            {wp.i18n.__( 'Copy', 'cf7-vk' )}
                                        </button>
                                    </div>
                                </>
                            ) : (
                                <input
                                    className="edit-command"
                                    type="text"
                                    value={form.authCommand}
                                    onChange={updateField('authCommand')}
                                    onBlur={() => commitInlineEdit('authCommand')}
                                    onKeyDown={handleInlineEditorKeyDown('authCommand')}
                                    autoFocus
                                    disabled={saving}
                                    spellCheck="false"
                                />
                            )}
                        </div>
                    </div>

                    <div className="bot-token">
                        <div
                            className={`show-token${bot.isAccessTokenDefinedByConst ? ' const' : ''}`}
                            onClick={startEditingToken}
                            title={bot.isAccessTokenDefinedByConst
                                ? wp.i18n.__( 'Defined by PHP constant', 'cf7-vk' )
                                : wp.i18n.__( 'Click to edit token', 'cf7-vk' )}
                        >
                            {wp.i18n.__( 'token', 'cf7-vk' )}: <span className={`token-value${hasTokenValue || bot.isAccessTokenDefinedByConst ? '' : ' empty'}`}>{isEditingToken ? '' : tokenDisplay}</span>
                        </div>

                        {isEditingToken ? (
                            <input
                                className="edit-token"
                                type="text"
                                value={form.accessToken}
                                onChange={updateField('accessToken')}
                                onBlur={() => commitInlineEdit('accessToken')}
                                onKeyDown={handleInlineEditorKeyDown('accessToken')}
                                autoFocus
                                disabled={saving}
                                spellCheck="false"
                            />
                        ) : null}
                    </div>
                </div>

                {feedback?.message ? (
                    <div className="frame bot-feedback error">
                        <strong>{feedback.message}</strong>
                    </div>
                ) : null}

                <div className="frame bot-fields">
                    <div className="bot-field-grid">
                        <label className="bot-field">
                            <span>{wp.i18n.__( 'Group ID', 'cf7-vk' )}</span>
                            <input value={form.groupId} onChange={updateField('groupId')} onBlur={handleFieldBlur} disabled={saving} />
                        </label>
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
                    ) : !hasConfiguredBot ? (
                        <span className="unknown-bot-status-message">
                            {wp.i18n.__( 'Fill in group ID and access token to start automatic listening.', 'cf7-vk' )}
                        </span>
                    ) : 'unknown' === statusClass ? (
                        <span className="unknown-bot-status-message">
                            {wp.i18n.__( 'Automatic connection check is in progress.', 'cf7-vk' )}
                        </span>
                    ) : (
                        <span className="no-chats-found">
                            {wp.i18n.__( 'Waiting for VK dialogs to join through the auth command...', 'cf7-vk' )}
                        </span>
                    )}
                </div>

                <div className="frame status-bar">
                    <div className="bot-actions">
                        <button className="remove-bot-button" type="button" onClick={remove} disabled={saving}>
                            {wp.i18n.__( 'Remove bot', 'cf7-vk' )}
                        </button>
                    </div>
                    <div className={`bot-status ${statusClass}`}>{statusClass}</div>
                </div>
            </div>
        </div>
    );
};

export default BotView;
