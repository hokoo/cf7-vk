/* global wp */

export function getChatStatus(botId, chatId, relations = []) {
    const relation = relations.find((item) => item?.data?.from === botId && item?.data?.to === chatId);
    const status = relation?.data?.meta?.status?.[0];

    if ('muted' === status) {
        return wp.i18n.__( 'Muted', 'cf7-vk' );
    }

    if ('pending' === status || !status) {
        return wp.i18n.__( 'Pending', 'cf7-vk' );
    }

    return wp.i18n.__( 'Active', 'cf7-vk' );
}

export function getToggleButtonLabel(status) {
    switch (status.toLowerCase()) {
        case 'active':
            return wp.i18n.__( 'Mute', 'cf7-vk' );
        case 'muted':
            return wp.i18n.__( 'Unmute', 'cf7-vk' );
        case 'pending':
        default:
            return wp.i18n.__( 'Activate', 'cf7-vk' );
    }
}
