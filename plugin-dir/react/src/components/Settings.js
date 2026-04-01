/* global cf7VkData, wp */

import React, {useEffect, useState} from 'react';
import {apiFetchSettings, apiSaveSettings} from '../utils/api';

const Settings = () => {
    const [enabled, setEnabled] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const load = async () => {
            const settings = await apiFetchSettings();
            setEnabled(Boolean(settings?.[cf7VkData.options.early_access]));
            setLoaded(true);
        };

        load();
    }, []);

    const onToggle = async (event) => {
        const nextValue = event.target.checked;
        setEnabled(nextValue);
        setSaving(true);

        try {
            await apiSaveSettings({[cf7VkData.options.early_access]: nextValue});
        } finally {
            setSaving(false);
        }
    };

    return (
        <section className="cf7vk-card">
            <h2>{wp.i18n.__( 'Plugin settings', 'cf7-vk' )}</h2>
            <p className="cf7vk-hint">
                {wp.i18n.__( 'Long Poll discovery runs manually from each bot card. Enable pre-releases only if you want experimental updates.', 'cf7-vk' )}
            </p>
            {!loaded ? (
                <div>{wp.i18n.__( 'Loading settings...', 'cf7-vk' )}</div>
            ) : (
                <label className="cf7vk-form">
                    <span>{wp.i18n.__( 'Install pre-releases', 'cf7-vk' )}</span>
                    <input
                        type="checkbox"
                        checked={enabled}
                        onChange={onToggle}
                        disabled={saving}
                    />
                </label>
            )}
        </section>
    );
};

export default Settings;
