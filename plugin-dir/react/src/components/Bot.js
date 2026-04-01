/* global wp */

import React, {useState} from 'react';
import {apiDeleteBot, apiPingBot, apiSaveBot} from '../utils/api';

const Bot = ({bot, onUpdated}) => {
    const [form, setForm] = useState({
        title: bot.title?.rendered || '',
        groupId: bot.groupId || '',
        accessToken: bot.accessToken || '',
        apiVersion: bot.apiVersion || '5.199',
        authCommand: bot.authCommand || 'start'
    });
    const [saving, setSaving] = useState(false);
    const [pinging, setPinging] = useState(false);
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
                </dl>

                <div className="cf7vk-actions">
                    <button className="button button-primary" type="submit" disabled={saving}>
                        {wp.i18n.__( 'Save', 'cf7-vk' )}
                    </button>
                    <button className="button" type="button" onClick={ping} disabled={saving || pinging}>
                        {pinging ? wp.i18n.__( 'Checking...', 'cf7-vk' ) : wp.i18n.__( 'Check connection', 'cf7-vk' )}
                    </button>
                    <button className="button" type="button" onClick={remove} disabled={saving}>
                        {wp.i18n.__( 'Remove', 'cf7-vk' )}
                    </button>
                </div>
            </form>
        </article>
    );
};

export default Bot;
