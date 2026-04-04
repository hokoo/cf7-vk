/* global wp */

export function getChatStatus(botId, chatId, relations = []) {
    const relation = relations.find((item) => item?.data?.from === botId && item?.data?.to === chatId);
    const status = relation?.data?.meta?.status?.[0];

    if ('muted' === status) {
        return wp.i18n.__( 'Muted', 'vk-notifications-for-contact-form-7' );
    }

    if ('pending' === status || !status) {
        return wp.i18n.__( 'Pending', 'vk-notifications-for-contact-form-7' );
    }

    return wp.i18n.__( 'Active', 'vk-notifications-for-contact-form-7' );
}

export function getToggleButtonLabel(status) {
    switch (status.toLowerCase()) {
        case 'active':
            return wp.i18n.__( 'Mute', 'vk-notifications-for-contact-form-7' );
        case 'muted':
            return wp.i18n.__( 'Unmute', 'vk-notifications-for-contact-form-7' );
        case 'pending':
        default:
            return wp.i18n.__( 'Activate', 'vk-notifications-for-contact-form-7' );
    }
}
