/* global wp */

import React, {useState} from 'react';
import {apiCreateBot} from '../utils/api';

const NewBot = ({onCreated}) => {
    const [saving, setSaving] = useState(false);

    const handleCreateBot = async () => {
        setSaving(true);

        try {
            await apiCreateBot({
                title: wp.i18n.__( 'VK Bot', 'cf7-vk' ),
                groupId: '',
                accessToken: '',
                apiVersion: '5.199',
                authCommand: 'start'
            });
            await onCreated();
        } catch (error) {
            console.error('Error creating bot:', error);
            alert(wp.i18n.__( 'Failed to create bot', 'cf7-vk' ));
        } finally {
            setSaving(false);
        }
    };

    return (
        <button className="add-button add-bot-button" onClick={handleCreateBot} disabled={saving}>
            {wp.i18n.__( 'Create Bot', 'cf7-vk' )}
        </button>
    );
};

export default NewBot;
