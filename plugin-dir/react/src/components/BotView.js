/* global wp, cf7vkData */

import React from 'react';
import {copyWithTooltip} from '../utils/main';
import {getChatStatus, getToggleButtonLabel} from '../utils/chatStatus';
import {formatBotTitle} from '../utils/botTitle';

const buildPhpConstSnippet = (constName, placeholder) => `const ${constName} = '${placeholder}';`;

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
    const botTitle = bot.title?.rendered || wp.i18n.__( 'Untitled bot', 'message-bridge-for-contact-form-7-and-vk' );
    const visibleBotTitle = formatBotTitle(botTitle);
    const authCommand = form.authCommand.trim() || 'start';
    const emptySecret = cf7vkData?.phrases?.emptySecret || wp.i18n.__( '[empty]', 'message-bridge-for-contact-form-7-and-vk' );
    const phpConstDefinedLabel = wp.i18n.__( 'Defined by PHP constant', 'message-bridge-for-contact-form-7-and-vk' );
    const displayedTokenValue = bot.isAccessTokenDefinedByConst ? (bot.accessToken || '') : form.accessToken;
    const hasTokenValue = Boolean(displayedTokenValue.trim()) && displayedTokenValue !== emptySecret;
    const tokenDisplay = hasTokenValue ? `***${displayedTokenValue.slice(-4)}` : emptySecret;
    const accessTokenConstSnippet = bot.accessTokenConst
        ? buildPhpConstSnippet(bot.accessTokenConst, 'your_access_token')
        : '';
    const groupIdConstSnippet = bot.groupIdConst
        ? buildPhpConstSnippet(bot.groupIdConst, 'your_group_id')
        : '';
    const groupIdInputId = `bot-${bot.id}-group-id`;

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
                                            {wp.i18n.__( 'Edit', 'message-bridge-for-contact-form-7-and-vk' )}
                                        </button>
                                        <button
                                            className="command-action right copyable"
                                            type="button"
                                            onClick={(event) => copyWithTooltip(event.currentTarget, authCommand)}
                                            title={wp.i18n.__( 'Copy authorization command', 'message-bridge-for-contact-form-7-and-vk' )}
                                            disabled={saving}
                                        >
                                            {wp.i18n.__( 'Copy', 'message-bridge-for-contact-form-7-and-vk' )}
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
                                ? phpConstDefinedLabel
                                : wp.i18n.__( 'Click to edit token', 'message-bridge-for-contact-form-7-and-vk' )}
                        >
                            {wp.i18n.__( 'token', 'message-bridge-for-contact-form-7-and-vk' )}: <span className={`token-value${hasTokenValue ? '' : ' empty'}`}>{isEditingToken ? '' : tokenDisplay}</span>
                        </div>

                        {!isEditingToken && !bot.isAccessTokenDefinedByConst && accessTokenConstSnippet ? (
                            <button
                                className="php-const-hint copyable"
                                type="button"
                                title={wp.i18n.__( 'Copy PHP code for the access token constant', 'message-bridge-for-contact-form-7-and-vk' )}
                                onClick={(event) => copyWithTooltip(event.currentTarget, accessTokenConstSnippet)}
                                disabled={saving}
                            >
                                {wp.i18n.__( 'Copy PHP const', 'message-bridge-for-contact-form-7-and-vk' )}
                            </button>
                        ) : null}

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
                        <div
                            className={`bot-field${bot.isGroupIdDefinedByConst ? ' is-const' : ''}`}
                            title={bot.isGroupIdDefinedByConst ? phpConstDefinedLabel : undefined}
                        >
                            <span className="bot-field-heading">
                                <label htmlFor={groupIdInputId}>{wp.i18n.__( 'Group ID', 'message-bridge-for-contact-form-7-and-vk' )}</label>
                                {!bot.isGroupIdDefinedByConst && groupIdConstSnippet ? (
                                    <button
                                        className="bot-field-const-copy copyable"
                                        type="button"
                                        title={wp.i18n.__( 'Copy PHP code for the Group ID constant', 'message-bridge-for-contact-form-7-and-vk' )}
                                        onClick={(event) => copyWithTooltip(event.currentTarget, groupIdConstSnippet)}
                                        disabled={saving}
                                    >
                                        {wp.i18n.__( 'Copy PHP const', 'message-bridge-for-contact-form-7-and-vk' )}
                                    </button>
                                ) : null}
                            </span>
                            <input
                                id={groupIdInputId}
                                value={form.groupId}
                                onChange={updateField('groupId')}
                                onBlur={handleFieldBlur}
                                disabled={saving || bot.isGroupIdDefinedByConst}
                                title={bot.isGroupIdDefinedByConst
                                    ? phpConstDefinedLabel
                                    : undefined}
                            />
                        </div>
                    </div>
                </div>

                <div className="frame chats-for-bot">
                    <h5>{wp.i18n.__( 'Linked dialogs', 'message-bridge-for-contact-form-7-and-vk' )}</h5>

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
                                            {wp.i18n.__( 'Remove', 'message-bridge-for-contact-form-7-and-vk' )}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : 'offline' === statusClass ? (
                        <span className="offline-bot-sad-message">
                            {wp.i18n.__( 'Couldn’t load dialogs from VK right now.', 'message-bridge-for-contact-form-7-and-vk' )}
                        </span>
                    ) : !hasConfiguredBot ? (
                        <span className="unknown-bot-status-message">
                            {wp.i18n.__( 'Fill in group ID and access token to start automatic listening.', 'message-bridge-for-contact-form-7-and-vk' )}
                        </span>
                    ) : 'unknown' === statusClass ? (
                        <span className="unknown-bot-status-message">
                            {wp.i18n.__( 'Automatic connection check is in progress.', 'message-bridge-for-contact-form-7-and-vk' )}
                        </span>
                    ) : (
                        <span className="no-chats-found">
                            {wp.i18n.__( 'Waiting for VK dialogs to join through the auth command...', 'message-bridge-for-contact-form-7-and-vk' )}
                        </span>
                    )}
                </div>

                <div className="frame status-bar">
                    <div className="bot-actions">
                        <button className="remove-bot-button" type="button" onClick={remove} disabled={saving}>
                            {wp.i18n.__( 'Remove bot', 'message-bridge-for-contact-form-7-and-vk' )}
                        </button>
                    </div>
                    <div className={`bot-status ${statusClass}`}>{statusClass}</div>
                </div>
            </div>
        </div>
    );
};

export default BotView;
