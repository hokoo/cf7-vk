/* global wp */

import React, {useState} from 'react';
import {apiCreateChannel} from '../utils/api';

const NewChannel = ({onCreated}) => {
    const [saving, setSaving] = useState(false);

    const handleCreateChannel = async () => {
        setSaving(true);

        try {
            const createdChannel = await apiCreateChannel(wp.i18n.__( 'Channel', 'message-bridge-for-contact-form-7-and-vk' ));
            onCreated(createdChannel);
        } catch (error) {
            console.error('Error creating channel:', error);
            alert(wp.i18n.__( 'Failed to create channel', 'message-bridge-for-contact-form-7-and-vk' ));
        } finally {
            setSaving(false);
        }
    };

    return (
        <button className="add-button add-channel-button" onClick={handleCreateChannel} disabled={saving}>
            {wp.i18n.__( 'Create Channel', 'message-bridge-for-contact-form-7-and-vk' )}
        </button>
    );
};

export default NewChannel;
