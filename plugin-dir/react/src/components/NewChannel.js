/* global wp */

import React, {useState} from 'react';
import {apiCreateChannel} from '../utils/api';

const NewChannel = ({onCreated}) => {
    const [title, setTitle] = useState('');
    const [saving, setSaving] = useState(false);

    const handleSubmit = async (event) => {
        event.preventDefault();

        if (!title.trim()) {
            return;
        }

        setSaving(true);

        try {
            await apiCreateChannel(title);
            setTitle('');
            await onCreated();
        } finally {
            setSaving(false);
        }
    };

    return (
        <section className="cf7vk-card">
            <h2>{wp.i18n.__( 'Add channel', 'cf7-vk' )}</h2>
            <form className="cf7vk-form" onSubmit={handleSubmit}>
                <label>
                    <span>{wp.i18n.__( 'Channel title', 'cf7-vk' )}</span>
                    <input value={title} onChange={(event) => setTitle(event.target.value)} />
                </label>

                <div className="cf7vk-actions">
                    <button className="button button-primary" type="submit" disabled={saving}>
                        {wp.i18n.__( 'Create channel', 'cf7-vk' )}
                    </button>
                </div>
            </form>
        </section>
    );
};

export default NewChannel;
