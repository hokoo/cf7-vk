/* global wp */

import React, {useCallback, useEffect, useState} from 'react';
import Channel from './components/Channel';
import Bot from './components/Bot';
import NewBot from './components/NewBot';
import NewChannel from './components/NewChannel';
import {
    fetchBots,
    fetchChats,
    fetchChannels,
    fetchForms,
    fetchBotsForChats,
    fetchChatsForChannels,
    fetchBotsForChannels,
    fetchFormsForChannels
} from './utils/api';

const normalizeList = (items) => Array.isArray(items) ? items : [];
const sortById = (items) => [...items].sort((left, right) => (left?.id || 0) - (right?.id || 0));
const replaceById = (items, nextItem) => sortById([
    ...items.filter((item) => item?.id !== nextItem?.id),
    nextItem
]);

const RESOURCE_NAMES = [
    'bots',
    'channels',
    'chats',
    'forms',
    'bot2ChatConnections',
    'chat2ChannelConnections',
    'bot2ChannelConnections',
    'form2ChannelConnections',
];

const createResourceStates = () => RESOURCE_NAMES.reduce((states, name) => ({
    ...states,
    [name]: {status: 'idle', error: null},
}), {});

export class SettingsErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = {hasError: false, retryKey: 0};
    }

    static getDerivedStateFromError() {
        return {hasError: true};
    }

    componentDidCatch() {
        console.error('CF7 VK settings failed to render.');
    }

    retry = () => {
        this.setState((state) => ({hasError: false, retryKey: state.retryKey + 1}));
    };

    render() {
        if (this.state.hasError) {
            return (
                <div className="cf7vk-error-boundary" role="alert">
                    <p>{wp.i18n.__( 'The settings screen could not be displayed.', 'message-bridge-for-contact-form-7-and-vk' )}</p>
                    <button type="button" className="button" onClick={this.retry}>
                        {wp.i18n.__( 'Try again', 'message-bridge-for-contact-form-7-and-vk' )}
                    </button>
                </div>
            );
        }

        return <React.Fragment key={this.state.retryKey}>{this.props.children}</React.Fragment>;
    }
}

const SettingsApp = () => {
    const [bots, setBots] = useState([]);
    const [channels, setChannels] = useState([]);
    const [chats, setChats] = useState([]);
    const [forms, setForms] = useState([]);
    const [bot2ChatConnections, setBot2ChatConnections] = useState([]);
    const [chat2ChannelConnections, setChat2ChannelConnections] = useState([]);
    const [bot2ChannelConnections, setBot2ChannelConnections] = useState([]);
    const [form2ChannelConnections, setForm2ChannelConnections] = useState([]);
    const [resources, setResources] = useState(createResourceStates);

    const loadResource = useCallback(async (name, loader, apply) => {
        setResources((previous) => ({
            ...previous,
            [name]: {status: 'loading', error: null},
        }));

        try {
            const data = normalizeList(await loader());
            apply(data);
            setResources((previous) => ({
                ...previous,
                [name]: {status: 'success', error: null},
            }));
            return data;
        } catch (error) {
            setResources((previous) => ({
                ...previous,
                [name]: {status: 'error', error},
            }));
            throw error;
        }
    }, []);

    const loadResources = useCallback((names = RESOURCE_NAMES) => Promise.allSettled(
        names.map((name) => {
            switch (name) {
                case 'bots':
                    return loadResource(name, fetchBots, setBots);
                case 'channels':
                    return loadResource(name, fetchChannels, setChannels);
                case 'chats':
                    return loadResource(name, fetchChats, setChats);
                case 'forms':
                    return loadResource(name, fetchForms, setForms);
                case 'bot2ChatConnections':
                    return loadResource(name, fetchBotsForChats, setBot2ChatConnections);
                case 'chat2ChannelConnections':
                    return loadResource(name, fetchChatsForChannels, setChat2ChannelConnections);
                case 'bot2ChannelConnections':
                    return loadResource(name, fetchBotsForChannels, setBot2ChannelConnections);
                case 'form2ChannelConnections':
                    return loadResource(name, fetchFormsForChannels, setForm2ChannelConnections);
                default:
                    return Promise.resolve();
            }
        })
    ), [loadResource]);

    const refreshBots = useCallback(
        async () => loadResource('bots', fetchBots, setBots),
        [loadResource]
    );

    const refreshChannels = useCallback(
        async () => loadResource('channels', fetchChannels, setChannels),
        [loadResource]
    );

    const refreshChats = useCallback(
        async () => loadResource('chats', fetchChats, setChats),
        [loadResource]
    );

    const refreshBotChatConnections = useCallback(
        async () => loadResource('bot2ChatConnections', fetchBotsForChats, setBot2ChatConnections),
        [loadResource]
    );

    const refreshChatChannelConnections = useCallback(
        async () => loadResource('chat2ChannelConnections', fetchChatsForChannels, setChat2ChannelConnections),
        [loadResource]
    );

    const refreshBotChannelConnections = useCallback(
        async () => loadResource('bot2ChannelConnections', fetchBotsForChannels, setBot2ChannelConnections),
        [loadResource]
    );

    const refreshFormChannelConnections = useCallback(
        async () => loadResource('form2ChannelConnections', fetchFormsForChannels, setForm2ChannelConnections),
        [loadResource]
    );

    const refreshBotRuntime = useCallback(async () => {
        await Promise.all([
            refreshBots(),
            refreshChats(),
            refreshBotChatConnections()
        ]);
    }, [refreshBotChatConnections, refreshBots, refreshChats]);

    const handleBotCreated = (createdBot) => {
        if (!createdBot?.id) {
            refreshBots();
            return;
        }

        setBots((current) => replaceById(current, createdBot));
    };

    const handleBotSaved = (savedBot) => {
        if (!savedBot?.id) {
            refreshBots();
            return;
        }

        setBots((current) => replaceById(current, savedBot));
    };

    const handleBotRemoved = (botId) => {
        setBots((current) => current.filter((bot) => bot.id !== botId));
        setBot2ChatConnections((current) => current.filter((item) => item?.data?.from !== botId));
        setBot2ChannelConnections((current) => current.filter((item) => item?.data?.from !== botId));
    };

    const handleChannelCreated = (createdChannel) => {
        if (!createdChannel?.id) {
            refreshChannels();
            return;
        }

        setChannels((current) => replaceById(current, createdChannel));
    };

    const handleChannelSaved = (savedChannel) => {
        if (!savedChannel?.id) {
            refreshChannels();
            return;
        }

        setChannels((current) => replaceById(current, savedChannel));
    };

    const handleChannelRemoved = (channelId) => {
        setChannels((current) => current.filter((channel) => channel.id !== channelId));
        setBot2ChannelConnections((current) => current.filter((item) => item?.data?.to !== channelId));
        setChat2ChannelConnections((current) => current.filter((item) => item?.data?.to !== channelId));
        setForm2ChannelConnections((current) => current.filter((item) => item?.data?.to !== channelId));
    };

    useEffect(() => {
        loadResources();
    }, [loadResources]);

    const resourceStatus = (name) => resources[name]?.status ?? 'idle';
    const resourceAvailability = (...names) => {
        if (names.some((name) => 'error' === resourceStatus(name))) {
            return 'error';
        }

        return names.every((name) => 'success' === resourceStatus(name)) ? 'ready' : 'loading';
    };

    const failedResources = RESOURCE_NAMES.filter((name) => 'error' === resourceStatus(name));
    const hasSettledResource = RESOURCE_NAMES.some(
        (name) => ['success', 'error'].includes(resourceStatus(name))
    );

    if (!hasSettledResource) {
        return <div>{wp.i18n.__( 'Loading data...', 'message-bridge-for-contact-form-7-and-vk' )}</div>;
    }

    return (
        <>
            <h1>{wp.i18n.__( 'VK Message Bridge Settings', 'message-bridge-for-contact-form-7-and-vk' )}</h1>
            {failedResources.length > 0 ? (
                <div className="notice notice-error cf7vk-notice cf7vk-load-status" role="alert">
                    <p>{wp.i18n.__( 'Some settings data could not be loaded.', 'message-bridge-for-contact-form-7-and-vk' )}</p>
                    <button
                        type="button"
                        className="button"
                        onClick={() => loadResources(failedResources)}
                    >
                        {wp.i18n.__( 'Retry failed requests', 'message-bridge-for-contact-form-7-and-vk' )}
                    </button>
                </div>
            ) : null}
            <div className="cf7-tg-container" id="cf7-vk-container">
                <div className="main-container">
                    <div className="list-container bots-container">
                        <div className="title-container">
                            <h3 className="title">{wp.i18n.__( 'Bots', 'message-bridge-for-contact-form-7-and-vk' )}</h3>
                            <NewBot onCreated={handleBotCreated} disabled={'success' !== resourceStatus('bots')} />
                        </div>

                        <div className="bot-list">
                            {'error' === resourceStatus('bots') ? (
                                <p className="resource-error">{wp.i18n.__( 'Bots could not be loaded.', 'message-bridge-for-contact-form-7-and-vk' )}</p>
                            ) : null}
                            {bots.map((bot) => (
                                <Bot
                                    key={bot.id}
                                    bot={bot}
                                    chats={chats}
                                    bot2ChatConnections={bot2ChatConnections}
                                    chatDataStatus={resourceAvailability('chats', 'bot2ChatConnections')}
                                    onBotSaved={handleBotSaved}
                                    onBotRemoved={handleBotRemoved}
                                    refreshBots={refreshBots}
                                    refreshBotRuntime={refreshBotRuntime}
                                    refreshBotChatConnections={refreshBotChatConnections}
                                    refreshBotChannelConnections={refreshBotChannelConnections}
                                    refreshChatChannelConnections={refreshChatChannelConnections}
                                />
                            ))}
                        </div>
                    </div>

                    <div className="list-container channels-container">
                        <div className="title-container">
                            <h3 className="title">{wp.i18n.__( 'Channels', 'message-bridge-for-contact-form-7-and-vk' )}</h3>
                            <NewChannel onCreated={handleChannelCreated} disabled={'success' !== resourceStatus('channels')} />
                        </div>

                        <div className="channel-list">
                            {'error' === resourceStatus('channels') ? (
                                <p className="resource-error">{wp.i18n.__( 'Channels could not be loaded.', 'message-bridge-for-contact-form-7-and-vk' )}</p>
                            ) : null}
                            {channels.map((channel) => (
                                <Channel
                                    key={channel.id}
                                    channel={channel}
                                    bots={bots}
                                    chats={chats}
                                    forms={forms}
                                    bot2ChatConnections={bot2ChatConnections}
                                    chat2ChannelConnections={chat2ChannelConnections}
                                    bot2ChannelConnections={bot2ChannelConnections}
                                    form2ChannelConnections={form2ChannelConnections}
                                    onChannelSaved={handleChannelSaved}
                                    onChannelRemoved={handleChannelRemoved}
                                    refreshBotChannelConnections={refreshBotChannelConnections}
                                    refreshChatChannelConnections={refreshChatChannelConnections}
                                    refreshFormChannelConnections={refreshFormChannelConnections}
                                    dataAvailability={{
                                        forms: resourceAvailability('forms', 'form2ChannelConnections'),
                                        bots: resourceAvailability('bots', 'bot2ChannelConnections'),
                                        chats: resourceAvailability(
                                            'bots',
                                            'bot2ChannelConnections',
                                            'chats',
                                            'bot2ChatConnections',
                                            'chat2ChannelConnections'
                                        ),
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            <style>
                {`.copyable::after { content: '` + wp.i18n.__( 'Copied!', 'message-bridge-for-contact-form-7-and-vk' ) + `' !important }`}
            </style>
        </>
    );
};

const App = () => (
    <SettingsErrorBoundary>
        <SettingsApp />
    </SettingsErrorBoundary>
);

export default App;
