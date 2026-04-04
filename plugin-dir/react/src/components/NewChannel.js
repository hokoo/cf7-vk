/* global wp */

import React, {useState} from 'react';
import {apiCreateChannel} from '../utils/api';

const NewChannel = ({onCreated}) => {
    const [saving, setSaving] = useState(false);

    const handleCreateChannel = async () => {
        setSaving(true);

        try {
            const createdChannel = await apiCreateChannel(wp.i18n.__( 'VK Channel', 'vk-notifications-for-contact-form-7' ));
            onCreated(createdChannel);
        } catch (error) {
            console.error('Error creating channel:', error);
            alert(wp.i18n.__( 'Failed to create channel', 'vk-notifications-for-contact-form-7' ));
        } finally {
            setSaving(false);
        }
    };

    return (
        <button className="add-button add-channel-button" onClick={handleCreateChannel} disabled={saving}>
            {wp.i18n.__( 'Create Channel', 'vk-notifications-for-contact-form-7' )}
        </button>
    );
};

export default NewChannel;
