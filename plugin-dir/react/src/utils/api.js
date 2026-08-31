/* global cf7vkData */

export class ApiError extends Error {
    constructor(message, details = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = details.status ?? 0;
        this.code = details.code ?? '';
        this.category = details.category ?? 'rest_transport';
        this.method = details.method ?? 'GET';
        this.url = details.url ?? '';
        this.data = details.data ?? null;
    }
}

const getApiErrorCategory = (status, code = '') => {
    if ([401, 403].includes(Number(status)) || /forbidden|unauthorized|cannot_/i.test(code)) {
        return 'rest_permission';
    }

    return Number(status) >= 400 ? 'rest_http' : 'rest_transport';
};

const safeText = (text) => String(text)
    .replace(/(^|[^A-Za-z0-9._-])vk1\.[A-Za-z0-9._-]{20,}(?=$|[^A-Za-z0-9._-])/gi, '$1[vk-access-token]')
    .replace(/([?&][^=]*(?:nonce|token|secret|password|key|peerid|email|phone)[^=]*=)[^&\s]*/gi, '$1[redacted]')
    .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[redacted]')
    .replace(/(^|[^\w])(?:\+\d[\d\s().-]{6,}\d|\d{3}[\s().-]\d{3}[\s().-]\d{4})(?=$|[^\w])/g, '$1[redacted]');

const safeData = (data) => {
    if (typeof data === 'string') {
        return safeText(data);
    }

    if (!data || typeof data !== 'object') {
        return data;
    }

    if (Array.isArray(data)) {
        return data.map(safeData);
    }

    return Object.entries(data).reduce((safe, [key, value]) => ({
        ...safe,
        [key]: /nonce|token|secret|password|key|peerid|email|phone/i.test(key) ? '[redacted]' : safeData(value),
    }), {});
};

const safeUrl = (url) => {
    try {
        const parsed = new URL(
            url,
            typeof window !== 'undefined' ? window.location?.origin ?? 'https://example.invalid' : 'https://example.invalid'
        );

        for (const key of Array.from(parsed.searchParams.keys())) {
            if (/nonce|token|secret|password|key|peerid|email|phone/i.test(key)) {
                parsed.searchParams.set(key, '[redacted]');
            }
        }

        return `${parsed.pathname}${parsed.search}${parsed.hash}`;
    } catch (error) {
        return safeText(url);
    }
};

const appendQueryParams = (url, params) => {
    const queryString = params.toString();

    if (!queryString) {
        return url;
    }

    return `${url}${url.includes('?') ? '&' : '?'}${queryString}`;
};

const forceDeleteUrl = (url, id) => appendQueryParams(`${url}${id}`, new URLSearchParams({force: 'true'}));

const apiRequest = async (url, method = 'GET', body = null, options = {}) => {
    const requestUrl = url;
    const query = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cf7vkData?.nonce
        }
    };

    if ((method === 'GET' || method === 'HEAD') && body) {
        const params = new URLSearchParams();

        Object.entries(body).forEach(([key, value]) => {
            if (typeof value === 'undefined' || value === null) {
                return;
            }

            params.append(key, value);
        });

        url = appendQueryParams(url, params);
    } else if (body) {
        query.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(url, query);
        let data;

        if (response.status === 204) {
            return options.includeResponse ? {data: true, response} : true;
        }

        try {
            data = await response.json();
        } catch (error) {
            throw new ApiError(
                response.ok ? 'The server returned an invalid response.' : 'The request could not be completed.',
                {
                    status: response.status ?? 0,
                    category: response.ok ? 'rest_parse' : getApiErrorCategory(response.status),
                    method,
                    url: safeUrl(url),
                }
            );
        }

        if (!response.ok) {
            throw new ApiError(
                'The request could not be completed.',
                {
                    status: response.status ?? data?.data?.status ?? 0,
                    code: data?.code ?? '',
                    category: getApiErrorCategory(response.status ?? data?.data?.status, data?.code),
                    method,
                    url: safeUrl(url),
                    data: safeData(data?.data ?? null),
                }
            );
        }

        return options.includeResponse ? {data, response} : data;
    } catch (error) {
        let normalizedError = error;

        if (!(normalizedError instanceof ApiError)) {
            normalizedError = new ApiError(
                safeText(error?.message || 'API request failed'),
                {
                    category: 'rest_transport',
                    method,
                    url: safeUrl(requestUrl),
                }
            );
        }

        console.error('API request error:', normalizedError);
        throw normalizedError;
    }
};

const mergePageItems = (items, pageItems) => {
    const seenIds = new Set(items.map(item => item?.id));

    return items.concat(
        pageItems.filter(item => {
            if (!item || typeof item.id === 'undefined') {
                return true;
            }

            if (seenIds.has(item.id)) {
                return false;
            }

            seenIds.add(item.id);
            return true;
        })
    );
};

const getTotalPages = (response) => {
    const totalPagesHeader = response.headers?.get?.('X-WP-TotalPages');
    const parsedTotalPages = Number.parseInt(totalPagesHeader, 10);

    return Number.isFinite(parsedTotalPages) && parsedTotalPages > 0 ? parsedTotalPages : null;
};

const fetchAllPages = async (url, params = {}, options = {}) => {
    const perPage = options.perPage ?? 100;
    let page = 1;
    let totalPages = 1;
    let items = [];

    do {
        const {data, response} = await apiRequest(
            url,
            'GET',
            {...params, per_page: perPage, page},
            {includeResponse: true}
        );

        items = mergePageItems(items, Array.isArray(data) ? data : []);

        const headerTotalPages = getTotalPages(response);
        if (headerTotalPages === null && options.stopWhenTotalPagesHeaderMissing) {
            totalPages = page;
        } else {
            totalPages = headerTotalPages ?? page;
        }

        page += 1;
    } while (page <= totalPages);

    return items;
};

const fetchWpPostCollection = async (url, params = {}) => fetchAllPages(
    url,
    {order: 'asc', orderby: 'id', ...params}
);

const fetchCf7FormsCollection = async () => {
    const perPage = 100;
    let offset = 0;
    let items = [];
    let keepFetching = true;

    while (keepFetching) {
        const pageItems = await apiRequest(
            cf7vkData.routes.forms,
            'GET',
            {per_page: perPage, offset, order: 'asc', orderby: 'id'}
        );
        const previousCount = items.length;
        const normalizedPageItems = Array.isArray(pageItems) ? pageItems : [];

        items = mergePageItems(items, normalizedPageItems);
        offset += normalizedPageItems.length;
        keepFetching = normalizedPageItems.length >= perPage && items.length > previousCount;
    }

    return items;
};

export const fetchBots = async () => fetchWpPostCollection(cf7vkData.routes.bots);
export const fetchChannels = async () => fetchWpPostCollection(cf7vkData.routes.channels);
export const fetchChats = async () => fetchWpPostCollection(cf7vkData.routes.chats);
export const fetchForms = async () => fetchCf7FormsCollection();
export const fetchBotsForChannels = async () => apiRequest(cf7vkData.routes.relations.bot2channel);
export const fetchBotsForChats = async () => apiRequest(cf7vkData.routes.relations.bot2chat);
export const fetchChatsForChannels = async () => apiRequest(cf7vkData.routes.relations.chat2channel);
export const fetchFormsForChannels = async () => apiRequest(cf7vkData.routes.relations.form2channel);

export const apiCreateBot = async ({title, groupId, accessToken, authCommand}) => {
    const payload = {
        title,
        status: 'publish',
        authCommand
    };

    if (undefined !== groupId) {
        payload.groupId = groupId;
    }

    if (undefined !== accessToken) {
        payload.accessToken = accessToken;
    }

    return apiRequest(cf7vkData.routes.bots, 'POST', payload);
};

export const apiSaveBot = async (botId, payload) => apiRequest(
    `${cf7vkData.routes.bots}${botId}`,
    'POST',
    payload
);

export const apiSaveBotCredentials = async (botId, payload) => apiRequest(
    `${cf7vkData.routes.bots}${botId}/credentials`,
    'POST',
    payload
);

export const apiDeleteBot = async (botId) => apiRequest(
    forceDeleteUrl(cf7vkData.routes.bots, botId),
    'DELETE'
);

export const apiPingBot = async (botId) => apiRequest(
    `${cf7vkData.routes.bots}${botId}/ping`,
    'POST'
);

export const apiFetchUpdates = async (botId) => apiRequest(
    `${cf7vkData.routes.bots}${botId}/fetch_updates`,
    'POST'
);

export const apiActivateBotChat = async (botId, chatId) => apiRequest(
    `${cf7vkData.routes.bots}${botId}/chats/${chatId}/activate`,
    'POST'
);

export const apiCreateChannel = async (title) => apiRequest(
    cf7vkData.routes.channels,
    'POST',
    {
        title,
        status: 'publish'
    }
);

export const apiSaveChannel = async (channelId, payload) => apiRequest(
    `${cf7vkData.routes.channels}${channelId}`,
    'POST',
    payload
);

export const apiDeleteChannel = async (channelId) => apiRequest(
    forceDeleteUrl(cf7vkData.routes.channels, channelId),
    'DELETE'
);

export const apiConnectBotToChannel = async (botId, channelId) => apiRequest(
    cf7vkData.routes.relations.bot2channel,
    'POST',
    {from: botId, to: channelId}
);

export const apiDisconnectBotFromChannel = async (connectionId) => apiRequest(
    `${cf7vkData.routes.relations.bot2channel}${connectionId}`,
    'DELETE'
);

export const apiDisconnectBotFromChat = async (connectionId) => apiRequest(
    `${cf7vkData.routes.relations.bot2chat}${connectionId}`,
    'DELETE'
);

export const apiSetBotChatStatus = async (connectionId, status) => apiRequest(
    `${cf7vkData.routes.relations.bot2chat}${connectionId}/meta`,
    'PATCH',
    {meta: [{key: 'status', value: status}]}
);

export const apiConnectChatToChannel = async (chatId, channelId) => apiRequest(
    cf7vkData.routes.relations.chat2channel,
    'POST',
    {from: chatId, to: channelId}
);

export const apiDisconnectChatFromChannel = async (connectionId) => apiRequest(
    `${cf7vkData.routes.relations.chat2channel}${connectionId}`,
    'DELETE'
);

export const apiConnectFormToChannel = async (formId, channelId) => apiRequest(
    cf7vkData.routes.relations.form2channel,
    'POST',
    {from: formId, to: channelId}
);

export const apiDisconnectFormFromChannel = async (connectionId) => apiRequest(
    `${cf7vkData.routes.relations.form2channel}${connectionId}`,
    'DELETE'
);

export const apiDeleteChat = async (chatId) => apiRequest(
    forceDeleteUrl(cf7vkData.routes.chats, chatId),
    'DELETE'
);
