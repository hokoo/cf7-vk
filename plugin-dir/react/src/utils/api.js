/* global cf7VkData */

const buildRequest = (url, method = 'GET', body = null) => {
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

    return {targetUrl, query};
};

const parseResponse = async (response) => {
    if (!response.ok) {
        let message = `Request failed with status ${response.status}`;
        let errorData = null;

        try {
            errorData = await response.json();
            message = errorData?.message || message;
        } catch (e) {
            // Ignore parsing errors and keep the fallback message.
        }

        const error = new Error(message);
        error.status = response.status;
        error.data = errorData;

        throw error;
    }

    if (response.status === 204) {
        return true;
    }

    return response.json();
};

const apiRequest = async (url, method = 'GET', body = null) => {
    const {targetUrl, query} = buildRequest(url, method, body);
    const response = await fetch(targetUrl, query);

    return parseResponse(response);
};

const apiCollectionRequest = async (url, params = {}) => {
    const items = [];
    let page = 1;
    let totalPages = 1;

    do {
        const {targetUrl, query} = buildRequest(
            url,
            'GET',
            {
                ...params,
                per_page: 100,
                page
            }
        );
        const response = await fetch(targetUrl, query);
        const pageItems = await parseResponse(response);
        const nextTotalPages = Number.parseInt(response.headers.get('X-WP-TotalPages') || '1', 10);

        items.push(...(Array.isArray(pageItems) ? pageItems : []));

        if (Number.isFinite(nextTotalPages) && nextTotalPages > 0) {
            totalPages = nextTotalPages;
        }

        page += 1;
    } while (page <= totalPages);

    return items;
};

export const fetchBots = async () => apiCollectionRequest(cf7VkData.routes.bots, {orderby: 'id', order: 'asc'});
export const fetchChannels = async () => apiCollectionRequest(cf7VkData.routes.channels, {orderby: 'id', order: 'asc'});
export const fetchChats = async () => apiCollectionRequest(cf7VkData.routes.chats, {orderby: 'id', order: 'asc'});
export const fetchForms = async () => apiRequest(cf7VkData.routes.forms);
export const fetchBotsForChannels = async () => apiRequest(cf7VkData.routes.relations.bot2channel);
export const fetchBotsForChats = async () => apiRequest(cf7VkData.routes.relations.bot2chat);
export const fetchChatsForChannels = async () => apiRequest(cf7VkData.routes.relations.chat2channel);
export const fetchFormsForChannels = async () => apiRequest(cf7VkData.routes.relations.form2channel);

export const apiCreateBot = async ({title, groupId, accessToken, authCommand}) => apiRequest(
    cf7VkData.routes.bots,
    'POST',
    {
        title,
        status: 'publish',
        groupId,
        accessToken,
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
    `${cf7VkData.routes.bots}${botId}/ping`,
    'POST'
);

export const apiFetchUpdates = async (botId) => apiRequest(
    `${cf7VkData.routes.bots}${botId}/fetch_updates`,
    'POST'
);

export const apiActivateBotChat = async (botId, chatId) => apiRequest(
    `${cf7VkData.routes.bots}${botId}/chats/${chatId}/activate`,
    'POST'
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
