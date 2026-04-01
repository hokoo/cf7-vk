/* global cf7VkData */

const apiRequest = async (url, method = 'GET', body = null) => {
    const query = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cf7VkData?.nonce
        }
    };

    let targetUrl = url;

    if ((method === 'GET' || method === 'HEAD') && body) {
        const params = new URLSearchParams();

        Object.entries(body).forEach(([key, value]) => {
            params.append(key, value);
        });

        targetUrl += `?${params.toString()}`;
    } else if (body) {
        query.body = JSON.stringify(body);
    }

    const response = await fetch(targetUrl, query);

    if (!response.ok) {
        let message = `Request failed with status ${response.status}`;

        try {
            const errorData = await response.json();
            message = errorData?.message || message;
        } catch (e) {
            // Ignore parsing errors and keep the fallback message.
        }

        throw new Error(message);
    }

    if (response.status === 204) {
        return true;
    }

    return response.json();
};

export const fetchBots = async () => apiRequest(cf7VkData.routes.bots, 'GET', {orderby: 'id', order: 'asc'});
export const fetchChannels = async () => apiRequest(cf7VkData.routes.channels, 'GET', {orderby: 'id', order: 'asc'});
export const fetchChats = async () => apiRequest(cf7VkData.routes.chats, 'GET', {orderby: 'id', order: 'asc'});
export const fetchForms = async () => apiRequest(cf7VkData.routes.forms);
export const fetchBotsForChannels = async () => apiRequest(cf7VkData.routes.relations.bot2channel);
export const fetchBotsForChats = async () => apiRequest(cf7VkData.routes.relations.bot2chat);
export const fetchChatsForChannels = async () => apiRequest(cf7VkData.routes.relations.chat2channel);
export const fetchFormsForChannels = async () => apiRequest(cf7VkData.routes.relations.form2channel);
export const apiFetchSettings = async () => apiRequest(cf7VkData.routes.settings);
export const apiSaveSettings = async (settings) => apiRequest(cf7VkData.routes.settings, 'POST', settings);

export const apiCreateBot = async ({title, groupId, accessToken, apiVersion, authCommand}) => apiRequest(
    cf7VkData.routes.bots,
    'POST',
    {
        title,
        status: 'publish',
        groupId,
        accessToken,
        apiVersion,
        authCommand
    }
);

export const apiSaveBot = async (botId, payload) => apiRequest(
    `${cf7VkData.routes.bots}${botId}`,
    'POST',
    payload
);

export const apiDeleteBot = async (botId) => apiRequest(
    `${cf7VkData.routes.bots}${botId}/?force=true`,
    'DELETE'
);

export const apiPingBot = async (botId) => apiRequest(
    `${cf7VkData.routes.bots}${botId}/ping`
);

export const apiFetchUpdates = async (botId) => apiRequest(
    `${cf7VkData.routes.bots}${botId}/fetch_updates`
);

export const apiCreateChannel = async (title) => apiRequest(
    cf7VkData.routes.channels,
    'POST',
    {
        title,
        status: 'publish'
    }
);

export const apiSaveChannel = async (channelId, payload) => apiRequest(
    `${cf7VkData.routes.channels}${channelId}`,
    'POST',
    payload
);

export const apiDeleteChannel = async (channelId) => apiRequest(
    `${cf7VkData.routes.channels}${channelId}/?force=true`,
    'DELETE'
);

export const apiConnectBotToChannel = async (botId, channelId) => apiRequest(
    cf7VkData.routes.relations.bot2channel,
    'POST',
    {from: botId, to: channelId}
);

export const apiDisconnectBotFromChannel = async (connectionId) => apiRequest(
    `${cf7VkData.routes.relations.bot2channel}${connectionId}`,
    'DELETE'
);

export const apiDisconnectBotFromChat = async (connectionId) => apiRequest(
    `${cf7VkData.routes.relations.bot2chat}${connectionId}`,
    'DELETE'
);

export const apiSetBotChatStatus = async (connectionId, status) => apiRequest(
    `${cf7VkData.routes.relations.bot2chat}${connectionId}/meta`,
    'PATCH',
    {meta: [{key: 'status', value: status}]}
);

export const apiConnectChatToChannel = async (chatId, channelId) => apiRequest(
    cf7VkData.routes.relations.chat2channel,
    'POST',
    {from: chatId, to: channelId}
);

export const apiDisconnectChatFromChannel = async (connectionId) => apiRequest(
    `${cf7VkData.routes.relations.chat2channel}${connectionId}`,
    'DELETE'
);

export const apiConnectFormToChannel = async (formId, channelId) => apiRequest(
    cf7VkData.routes.relations.form2channel,
    'POST',
    {from: formId, to: channelId}
);

export const apiDisconnectFormFromChannel = async (connectionId) => apiRequest(
    `${cf7VkData.routes.relations.form2channel}${connectionId}`,
    'DELETE'
);

export const apiDeleteChat = async (chatId) => apiRequest(
    `${cf7VkData.routes.chats}${chatId}/?force=true`,
    'DELETE'
);
