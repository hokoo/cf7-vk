/* global wp */

import React from 'react';
import Select from 'react-select';

const ChannelView = ({
    channel,
    titleValue,
    saving,
    error,
    handleTitleChange,
    handleKeyDown,
    saveTitle,
    botForChannel,
    renderedChats = [],
    formsForChannel = [],
    availableForms = [],
    showFormSelector,
    handleAddForm,
    handleFormSelect,
    handleRemoveForm,
    availableBots = [],
    handleBotSelect,
    handleRemoveBot,
    handleToggleChat,
    deleteChannel,
    getToggleButtonLabel,
    renderChannelClasses,
    dataAvailability = {forms: 'ready', bots: 'ready', chats: 'ready'}
}) => {
    return (
        <div
            className={`entity-container channel${renderChannelClasses()}${saving ? ' saving' : ''}`}
            data-testid={`cf7vk-channel-${channel.id}`}
            id={`channel-${channel.id}`}
        >
            <div className="entity-wrapper channel-wrapper">
                <div className="frame channel-title-wrapper">
                    <div className="columns">
                        <div className="column title-column">
                            <input
                                className="edit-title"
                                data-testid={`cf7vk-channel-title-input-${channel.id}`}
                                type="text"
                                value={titleValue}
                                onChange={handleTitleChange}
                                onKeyDown={handleKeyDown}
                                onBlur={saveTitle}
                                disabled={saving}
                            />
                            {error ? <small className="channel-error">{error}</small> : null}
                        </div>

                        <div className="column bot-column">
                            {'error' === dataAvailability.bots ? (
                                <span className="resource-error">
                                    {wp.i18n.__( 'VK bots are unavailable.', 'message-bridge-for-contact-form-7-and-vk' )}
                                </span>
                            ) : 'ready' !== dataAvailability.bots ? (
                                <span className="resource-loading">
                                    {wp.i18n.__( 'Loading VK bots...', 'message-bridge-for-contact-form-7-and-vk' )}
                                </span>
                            ) : botForChannel ? (
                                <div className={`bot-for-channel ${botForChannel.statusClass}`}>
                                    <span>{botForChannel.title}</span>
                                    <button
                                        className="detach-button detach-bot-button crux"
                                        data-testid={`cf7vk-channel-${channel.id}-remove-bot`}
                                        type="button"
                                        onClick={handleRemoveBot}
                                        disabled={saving}
                                        aria-label={wp.i18n.__( 'Remove bot from channel', 'message-bridge-for-contact-form-7-and-vk' )}
                                        title={wp.i18n.__( 'Remove bot from channel', 'message-bridge-for-contact-form-7-and-vk' )}
                                    />
                                </div>
                            ) : (
                                <>
                                    {availableBots.length > 0 ? (
                                        <Select
                                            className="select-picker bot-picker"
                                            classNamePrefix="select-picker"
                                            inputId={`cf7vk-channel-bot-picker-${channel.id}`}
                                            instanceId={`cf7vk-channel-bot-picker-${channel.id}`}
                                            data-testid={`cf7vk-channel-${channel.id}-bot-picker`}
                                            options={availableBots.map((bot) => ({
                                                value: bot.id,
                                                label: bot.title
                                            }))}
                                            isSearchable={false}
                                            placeholder={wp.i18n.__( 'Pick a VK bot', 'message-bridge-for-contact-form-7-and-vk' )}
                                            onChange={handleBotSelect}
                                            isClearable
                                            isDisabled={saving}
                                        />
                                    ) : (
                                        <span className="no-bots-found">
                                            [{wp.i18n.__( 'No VK bots available', 'message-bridge-for-contact-form-7-and-vk' )}]
                                        </span>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <div className="frame chats">
                    {'error' === dataAvailability.chats ? (
                        <span className="resource-error">
                            {wp.i18n.__( 'VK dialog data is unavailable.', 'message-bridge-for-contact-form-7-and-vk' )}
                        </span>
                    ) : 'ready' !== dataAvailability.chats ? (
                        <span className="resource-loading">
                            {wp.i18n.__( 'Loading VK dialog data...', 'message-bridge-for-contact-form-7-and-vk' )}
                        </span>
                    ) : renderedChats.length > 0 ? (
                        <>
                            {renderedChats.map((chat) => (
                                <div
                                    key={chat.id}
                                    className={`chat chat-${chat.id} ${chat.status.toLowerCase()}`}
                                    data-testid={`cf7vk-channel-${channel.id}-chat-${chat.id}`}
                                    role="button"
                                    tabIndex={0}
                                    onClick={() => !saving && handleToggleChat(chat.id, chat.status)}
                                    onKeyDown={(event) => {
                                        if (saving || !['Enter', ' '].includes(event.key)) {
                                            return;
                                        }

                                        event.preventDefault();
                                        handleToggleChat(chat.id, chat.status);
                                    }}
                                    aria-disabled={saving}
                                    title={getToggleButtonLabel(chat.status)}
                                >
                                    <span className="chat-username">{chat.title}</span>
                                </div>
                            ))}
                        </>
                    ) : (
                        <span className="no-chats-found">[{wp.i18n.__( 'No dialogs assigned to this channel', 'message-bridge-for-contact-form-7-and-vk' )}]</span>
                    )}
                </div>

                <div className="frame forms">
                    <button
                        className="add-button add-form-button"
                        data-testid={`cf7vk-channel-${channel.id}-add-form`}
                        type="button"
                        onClick={handleAddForm}
                        disabled={'ready' !== dataAvailability.forms || saving}
                    >
                        {!showFormSelector
                            ? wp.i18n.__( 'Add Form', 'message-bridge-for-contact-form-7-and-vk' )
                            : wp.i18n.__( 'Cancel', 'message-bridge-for-contact-form-7-and-vk' )}
                    </button>

                    {'error' === dataAvailability.forms ? (
                        <span className="resource-error">
                            {wp.i18n.__( 'Forms are unavailable.', 'message-bridge-for-contact-form-7-and-vk' )}
                        </span>
                    ) : 'ready' !== dataAvailability.forms ? (
                        <span className="resource-loading">
                            {wp.i18n.__( 'Loading forms...', 'message-bridge-for-contact-form-7-and-vk' )}
                        </span>
                    ) : showFormSelector ? (
                        <Select
                            className="select-picker form-picker"
                            classNamePrefix="select-picker"
                            inputId={`cf7vk-channel-form-picker-${channel.id}`}
                            instanceId={`cf7vk-channel-form-picker-${channel.id}`}
                            data-testid={`cf7vk-channel-${channel.id}-form-picker`}
                            options={availableForms.map((form) => ({
                                value: form.id,
                                label: form.title
                            }))}
                            isSearchable={true}
                            placeholder={wp.i18n.__( 'Pick a form', 'message-bridge-for-contact-form-7-and-vk' )}
                            onChange={handleFormSelect}
                            isClearable
                            isDisabled={saving}
                        />
                    ) : null}

                    {'ready' === dataAvailability.forms && (formsForChannel.length > 0 ? (
                        <ul className={`form-list ${showFormSelector ? 'show-selector' : ''}`}>
                            {formsForChannel.map((form) => (
                                <li key={form.id} data-testid={`cf7vk-channel-${channel.id}-form-${form.id}`}>
                                    {form.title}
                                    <button
                                        className="detach-button crux detach-form-button"
                                        data-testid={`cf7vk-channel-${channel.id}-remove-form-${form.id}`}
                                        type="button"
                                        onClick={() => handleRemoveForm(form.id)}
                                        disabled={saving}
                                        aria-label={wp.i18n.__( 'Remove form from channel', 'message-bridge-for-contact-form-7-and-vk' )}
                                        title={wp.i18n.__( 'Remove form from channel', 'message-bridge-for-contact-form-7-and-vk' )}
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : !showFormSelector ? (
                        <span className="no-forms-found">[{wp.i18n.__( 'No forms assigned to this channel', 'message-bridge-for-contact-form-7-and-vk' )}]</span>
                    ) : null)}
                </div>

                <div className="frame status-bar">
                    <button
                        className="remove-channel-button"
                        data-testid={`cf7vk-remove-channel-${channel.id}`}
                        type="button"
                        onClick={deleteChannel}
                        disabled={saving}
                    >
                        {wp.i18n.__( 'Remove channel', 'message-bridge-for-contact-form-7-and-vk' )}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ChannelView;
