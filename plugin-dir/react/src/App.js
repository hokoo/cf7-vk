/* global wp */

import React, {useEffect, useState} from 'react';
import Settings from './components/Settings';
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

    const loadData = async () => {
        setLoading(true);

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

            setBots(Array.isArray(loadedBots) ? loadedBots : []);
            setChannels(Array.isArray(loadedChannels) ? loadedChannels : []);
            setChats(Array.isArray(loadedChats) ? loadedChats : []);
            setForms(Array.isArray(loadedForms) ? loadedForms : []);
            setBot2ChatConnections(Array.isArray(loadedBotChatConnections) ? loadedBotChatConnections : []);
            setChat2ChannelConnections(Array.isArray(loadedChatChannelConnections) ? loadedChatChannelConnections : []);
            setBot2ChannelConnections(Array.isArray(loadedBotConnections) ? loadedBotConnections : []);
            setForm2ChannelConnections(Array.isArray(loadedFormConnections) ? loadedFormConnections : []);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    if (loading) {
        return <div>{wp.i18n.__( 'Loading data...', 'cf7-vk' )}</div>;
    }

    return (
        <>
            <h1>{wp.i18n.__( 'VK notificator settings', 'cf7-vk' )}</h1>
            <div className="cf7-tg-container" id="cf7-vk-container">
                <div className="settings-container">
                    <Settings />
                </div>

                <div className="main-container">
                    <div className="list-container bots-container">
                        <div className="title-container">
                            <h3 className="title">{wp.i18n.__( 'VK bots', 'cf7-vk' )}</h3>
                            <NewBot onCreated={loadData} />
                        </div>

                        <div className="bot-list">
                            {bots.map((bot) => (
                                <Bot
                                    key={bot.id}
                                    bot={bot}
                                    chats={chats}
                                    bot2ChatConnections={bot2ChatConnections}
                                    onUpdated={loadData}
                                />
                            ))}
                        </div>
                    </div>

                    <div className="list-container channels-container">
                        <div className="title-container">
                            <h3 className="title">{wp.i18n.__( 'Channels', 'cf7-vk' )}</h3>
                            <NewChannel onCreated={loadData} />
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
                                    onUpdated={loadData}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            <style>
                {`.copyable::after { content: '` + wp.i18n.__( 'Copied!', 'cf7-vk' ) + `' !important }`}
            </style>
        </>
    );
};

export default App;
