/* global wp */

import React, {useState} from 'react';
import {apiCreateBot} from '../utils/api';

const initialState = {
    title: '',
    groupId: '',
    accessToken: '',
    apiVersion: '5.199',
    authCommand: 'start'
};

const NewBot = ({onCreated}) => {
    const [form, setForm] = useState(initialState);
    const [saving, setSaving] = useState(false);

    const updateField = (field) => (event) => {
        setForm((current) => ({...current, [field]: event.target.value}));
    };

    const handleSubmit = async (event) => {
        event.preventDefault();

        if (!form.title.trim()) {
            return;
        }

        setSaving(true);

        try {
            await apiCreateBot(form);
            setForm(initialState);
            await onCreated();
        } finally {
            setSaving(false);
        }
    };

    return (
        <section className="cf7vk-card">
            <h2>{wp.i18n.__( 'Add VK bot', 'cf7-vk' )}</h2>
            <form className="cf7vk-form" onSubmit={handleSubmit}>
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
                    {wp.i18n.__( 'Users must send this exact command to the community before the dialog can be linked.', 'cf7-vk' )}
                </p>

                <div className="cf7vk-actions">
                    <button className="button button-primary" type="submit" disabled={saving}>
                        {wp.i18n.__( 'Create bot', 'cf7-vk' )}
                    </button>
                </div>
            </form>
        </section>
    );
};

export default NewBot;
