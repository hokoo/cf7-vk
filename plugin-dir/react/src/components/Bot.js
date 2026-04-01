/* global wp */

import React, {useState} from 'react';
import {apiDeleteBot, apiSaveBot} from '../utils/api';

const Bot = ({bot, onUpdated}) => {
    const [form, setForm] = useState({
        title: bot.title?.rendered || '',
        groupId: bot.groupId || '',
        accessToken: bot.accessToken || '',
        apiVersion: bot.apiVersion || '5.199',
        authCommand: bot.authCommand || 'start'
    });
    const [saving, setSaving] = useState(false);

    const updateField = (field) => (event) => {
        setForm((current) => ({...current, [field]: event.target.value}));
    };

    const save = async (event) => {
        event.preventDefault();
        setSaving(true);

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

    const statusClass = bot.lastStatus || 'unknown';

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

                <dl className="cf7vk-meta">
                    <dt>{wp.i18n.__( 'Token constant', 'cf7-vk' )}</dt>
                    <dd><code>{bot.accessTokenConst}</code></dd>
                    <dt>{wp.i18n.__( 'Stored by constant', 'cf7-vk' )}</dt>
                    <dd>{bot.isAccessTokenDefinedByConst ? 'yes' : 'no'}</dd>
                </dl>

                <div className="cf7vk-actions">
                    <button className="button button-primary" type="submit" disabled={saving}>
                        {wp.i18n.__( 'Save', 'cf7-vk' )}
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
