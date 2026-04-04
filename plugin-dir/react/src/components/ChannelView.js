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
    renderChannelClasses
}) => {
    return (
        <div className={`entity-container channel${renderChannelClasses()}${saving ? ' saving' : ''}`} id={`channel-${channel.id}`}>
            <div className="entity-wrapper channel-wrapper">
                <div className="frame channel-title-wrapper">
                    <div className="columns">
                        <div className="column title-column">
                            <input
                                className="edit-title"
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
                            {botForChannel ? (
                                <div className={`bot-for-channel ${botForChannel.statusClass}`}>
                                    <span>{botForChannel.title}</span>
                                    <button
                                        className="detach-button detach-bot-button crux"
                                        type="button"
                                        onClick={handleRemoveBot}
                                    />
                                </div>
                            ) : (
                                <>
                                    {availableBots.length > 0 ? (
                                        <Select
                                            className="select-picker bot-picker"
                                            classNamePrefix="select-picker"
                                            options={availableBots.map((bot) => ({
                                                value: bot.id,
                                                label: bot.title
                                            }))}
                                            isSearchable={false}
                                            placeholder={wp.i18n.__( 'Pick a VK bot', 'vk-notifications-for-contact-form-7' )}
                                            onChange={handleBotSelect}
                                            isClearable
                                        />
                                    ) : (
                                        <span className="no-bots-found">
                                            [{wp.i18n.__( 'No VK bots available', 'vk-notifications-for-contact-form-7' )}]
                                        </span>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <div className="frame chats">
                    {renderedChats.length > 0 ? (
                        <>
                            {renderedChats.map((chat) => (
                                <div
                                    key={chat.id}
                                    className={`chat chat-${chat.id} ${chat.status.toLowerCase()}`}
                                    onClick={() => handleToggleChat(chat.id, chat.status)}
                                    title={getToggleButtonLabel(chat.status)}
                                >
                                    <span className="chat-username">{chat.title}</span>
                                </div>
                            ))}
                        </>
                    ) : (
                        <span className="no-chats-found">[{wp.i18n.__( 'No dialogs assigned to this channel', 'vk-notifications-for-contact-form-7' )}]</span>
                    )}
                </div>

                <div className="frame forms">
                    <button className="add-button add-form-button" type="button" onClick={handleAddForm}>
                        {!showFormSelector
                            ? wp.i18n.__( 'Add Form', 'vk-notifications-for-contact-form-7' )
                            : wp.i18n.__( 'Cancel', 'vk-notifications-for-contact-form-7' )}
                    </button>

                    {showFormSelector ? (
                        <Select
                            className="select-picker form-picker"
                            classNamePrefix="select-picker"
                            options={availableForms.map((form) => ({
                                value: form.id,
                                label: form.title
                            }))}
                            isSearchable={true}
                            placeholder={wp.i18n.__( 'Pick a form', 'vk-notifications-for-contact-form-7' )}
                            onChange={handleFormSelect}
                            isClearable
                        />
                    ) : null}

                    {formsForChannel.length > 0 ? (
                        <ul className={`form-list ${showFormSelector ? 'show-selector' : ''}`}>
                            {formsForChannel.map((form) => (
                                <li key={form.id}>
                                    {form.title}
                                    <button
                                        className="detach-button crux detach-form-button"
                                        type="button"
                                        onClick={() => handleRemoveForm(form.id)}
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : !showFormSelector ? (
                        <span className="no-forms-found">[{wp.i18n.__( 'No forms assigned to this channel', 'vk-notifications-for-contact-form-7' )}]</span>
                    ) : null}
                </div>

                <div className="frame status-bar">
                    <button
                        className="remove-channel-button"
                        type="button"
                        onClick={deleteChannel}
                        disabled={saving}
                    >
                        {wp.i18n.__( 'Remove channel', 'vk-notifications-for-contact-form-7' )}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ChannelView;
