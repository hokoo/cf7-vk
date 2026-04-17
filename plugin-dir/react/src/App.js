/* global wp */

import React, {useEffect, useState} from 'react';
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

const App = () => {
    const [bots, setBots] = useState([]);
    const [channels, setChannels] = useState([]);
    const [chats, setChats] = useState([]);
    const [forms, setForms] = useState([]);
    const [bot2ChatConnections, setBot2ChatConnections] = useState([]);
    const [chat2ChannelConnections, setChat2ChannelConnections] = useState([]);
    const [bot2ChannelConnections, setBot2ChannelConnections] = useState([]);
    const [form2ChannelConnections, setForm2ChannelConnections] = useState([]);
    const [loading, setLoading] = useState(true);

    const loadInitialData = async () => {
        try {
            const [
                loadedBots,
                loadedChannels,
                loadedChats,
                loadedForms,
                loadedBotChatConnections,
                loadedChatChannelConnections,
                loadedBotConnections,
                loadedFormConnections
            ] = await Promise.all([
                fetchBots(),
                fetchChannels(),
                fetchChats(),
                fetchForms(),
                fetchBotsForChats(),
                fetchChatsForChannels(),
                fetchBotsForChannels(),
                fetchFormsForChannels()
            ]);

            setBots(normalizeList(loadedBots));
            setChannels(normalizeList(loadedChannels));
            setChats(normalizeList(loadedChats));
            setForms(normalizeList(loadedForms));
            setBot2ChatConnections(normalizeList(loadedBotChatConnections));
            setChat2ChannelConnections(normalizeList(loadedChatChannelConnections));
            setBot2ChannelConnections(normalizeList(loadedBotConnections));
            setForm2ChannelConnections(normalizeList(loadedFormConnections));
        } finally {
            setLoading(false);
        }
    };

    const refreshBots = async () => {
        const loadedBots = await fetchBots();
        setBots(normalizeList(loadedBots));
    };

    const refreshChannels = async () => {
        const loadedChannels = await fetchChannels();
        setChannels(normalizeList(loadedChannels));
    };

    const refreshChats = async () => {
        const loadedChats = await fetchChats();
        setChats(normalizeList(loadedChats));
    };

    const refreshBotChatConnections = async () => {
        const loadedConnections = await fetchBotsForChats();
        setBot2ChatConnections(normalizeList(loadedConnections));
    };

    const refreshChatChannelConnections = async () => {
        const loadedConnections = await fetchChatsForChannels();
        setChat2ChannelConnections(normalizeList(loadedConnections));
    };

    const refreshBotChannelConnections = async () => {
        const loadedConnections = await fetchBotsForChannels();
        setBot2ChannelConnections(normalizeList(loadedConnections));
    };

    const refreshFormChannelConnections = async () => {
        const loadedConnections = await fetchFormsForChannels();
        setForm2ChannelConnections(normalizeList(loadedConnections));
    };

    const refreshBotRuntime = async () => {
        await Promise.all([
            refreshBots(),
            refreshChats(),
            refreshBotChatConnections()
        ]);
    };

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
        loadInitialData();
    }, []);

    if (loading) {
        return <div>{wp.i18n.__( 'Loading data...', 'message-bridge-for-contact-form-7-and-vk' )}</div>;
    }

    return (
        <>
            <h1>{wp.i18n.__( 'VK Message Bridge Settings', 'message-bridge-for-contact-form-7-and-vk' )}</h1>
            <div className="cf7-tg-container" id="cf7-vk-container">
                <div className="main-container">
                    <div className="list-container bots-container">
                        <div className="title-container">
                            <h3 className="title">{wp.i18n.__( 'Bots', 'message-bridge-for-contact-form-7-and-vk' )}</h3>
                            <NewBot onCreated={handleBotCreated} />
                        </div>

                        <div className="bot-list">
                            {bots.map((bot) => (
                                <Bot
                                    key={bot.id}
                                    bot={bot}
                                    chats={chats}
                                    bot2ChatConnections={bot2ChatConnections}
                                    onBotSaved={handleBotSaved}
                                    onBotRemoved={handleBotRemoved}
                                    refreshBots={refreshBots}
                                    refreshBotRuntime={refreshBotRuntime}
                                    refreshBotChatConnections={refreshBotChatConnections}
                                    refreshChatChannelConnections={refreshChatChannelConnections}
                                />
                            ))}
                        </div>
                    </div>

                    <div className="list-container channels-container">
                        <div className="title-container">
                            <h3 className="title">{wp.i18n.__( 'Channels', 'message-bridge-for-contact-form-7-and-vk' )}</h3>
                            <NewChannel onCreated={handleChannelCreated} />
                        </div>

                        <div className="channel-list">
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

export default App;
