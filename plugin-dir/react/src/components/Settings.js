/* global cf7VkData, wp */

import React, {useEffect, useState} from 'react';
import {apiFetchSettings, apiSaveSettings} from '../utils/api';
import {sprintf} from '../utils/main';

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

    if (!loaded) {
        return <div>{wp.i18n.__( 'Loading setting...', 'cf7-vk' )}</div>;
    }

    return (
        <label className={`early-access ${enabled ? 'checked' : ''}`} disabled={saving}>
            <input
                type="checkbox"
                checked={enabled}
                onChange={onToggle}
                disabled={saving}
            />
            {wp.i18n.__( 'Install pre-releases (unstable, but exciting!)', 'cf7-vk' )}

            <small
                dangerouslySetInnerHTML={{
                    __html: sprintf(
                        wp.i18n.__(
                            'You might run into bugs. If that still sounds fine, I’d love to hear your %sfeedback on GitHub%s.',
                            'cf7-vk'
                        ),
                        '<a target="_blank" rel="noreferrer" href="https://github.com/hokoo/cf7-vk/issues">',
                        '</a>'
                    )
                }}
            />

            {enabled ? (
                <small
                    dangerouslySetInnerHTML={{
                        __html: wp.i18n.__(
                            'Long Poll dialog discovery is triggered manually from each bot card, so pre-release builds are best tested on a disposable local instance.',
                            'cf7-vk'
                        )
                    }}
                />
            ) : null}

            {enabled ? (
                <small
                    dangerouslySetInnerHTML={{
                        __html: sprintf(
                            wp.i18n.__( 'If you need authenticated update checks, place the token into the %s constant in wp-config.php.', 'cf7-vk' ),
                            '<code>CF7VK_GITHUB_TOKEN</code>'
                        )
                    }}
                />
            ) : null}
        </label>
    );
};

export default Settings;
