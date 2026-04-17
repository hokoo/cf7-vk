/* global wp */

import React, {useState} from 'react';
import {apiCreateBot} from '../utils/api';

const NewBot = ({onCreated}) => {
    const [saving, setSaving] = useState(false);

    const handleCreateBot = async () => {
        setSaving(true);

        try {
            const createdBot = await apiCreateBot({
                title: wp.i18n.__( 'VK Bot', 'message-bridge-for-contact-form-7-and-vk' ),
                groupId: '',
                accessToken: '',
                authCommand: 'start'
            });
            onCreated(createdBot);
        } catch (error) {
            console.error('Error creating bot:', error);
            alert(wp.i18n.__( 'Failed to create bot', 'message-bridge-for-contact-form-7-and-vk' ));
        } finally {
            setSaving(false);
        }
    };

    return (
        <button className="add-button add-bot-button" onClick={handleCreateBot} disabled={saving}>
            {wp.i18n.__( 'Create Bot', 'message-bridge-for-contact-form-7-and-vk' )}
        </button>
    );
};

export default NewBot;
