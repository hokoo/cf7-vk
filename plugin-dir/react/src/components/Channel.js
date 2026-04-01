/* global wp */

import React, {useMemo, useState} from 'react';
import {
    apiConnectBotToChannel,
    apiConnectFormToChannel,
    apiDeleteChannel,
    apiDisconnectBotFromChannel,
    apiDisconnectFormFromChannel
} from '../utils/api';

const Channel = ({
    channel,
    bots,
    forms,
    bot2ChannelConnections,
    form2ChannelConnections,
    onUpdated
}) => {
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
    const getFormTitle = (form) => form?.title?.rendered || form?.title || `#${form.id}`;
    const getBotTitle = (bot) => bot?.title?.rendered || bot?.title || `#${bot.id}`;

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

    return (
        <article className="cf7vk-card">
            <h3>{channel.title?.rendered || wp.i18n.__( 'Untitled channel', 'cf7-vk' )}</h3>

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
