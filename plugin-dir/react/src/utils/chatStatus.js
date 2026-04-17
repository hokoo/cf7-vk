/* global wp */

export function getChatStatus(botId, chatId, relations = []) {
    const relation = relations.find((item) => item?.data?.from === botId && item?.data?.to === chatId);
    const status = relation?.data?.meta?.status?.[0];

    if ('muted' === status) {
        return wp.i18n.__( 'Muted', 'message-bridge-for-contact-form-7-and-vk' );
    }

    if ('pending' === status || !status) {
        return wp.i18n.__( 'Pending', 'message-bridge-for-contact-form-7-and-vk' );
    }

    return wp.i18n.__( 'Active', 'message-bridge-for-contact-form-7-and-vk' );
}

export function getToggleButtonLabel(status) {
    switch (status.toLowerCase()) {
        case 'active':
            return wp.i18n.__( 'Mute', 'message-bridge-for-contact-form-7-and-vk' );
        case 'muted':
            return wp.i18n.__( 'Unmute', 'message-bridge-for-contact-form-7-and-vk' );
        case 'pending':
        default:
            return wp.i18n.__( 'Activate', 'message-bridge-for-contact-form-7-and-vk' );
    }
}
