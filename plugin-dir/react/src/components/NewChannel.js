/* global wp */

import React, {useState} from 'react';
import {apiCreateChannel} from '../utils/api';

const NewChannel = ({onCreated}) => {
    const [saving, setSaving] = useState(false);

    const handleCreateChannel = async () => {
        setSaving(true);

        try {
            await apiCreateChannel(wp.i18n.__( 'VK Channel', 'cf7-vk' ));
            await onCreated();
        } catch (error) {
            console.error('Error creating channel:', error);
            alert(wp.i18n.__( 'Failed to create channel', 'cf7-vk' ));
        } finally {
            setSaving(false);
        }
    };

    return (
        <button className="add-button add-channel-button" onClick={handleCreateChannel} disabled={saving}>
            {wp.i18n.__( 'Create Channel', 'cf7-vk' )}
        </button>
    );
};

export default NewChannel;
