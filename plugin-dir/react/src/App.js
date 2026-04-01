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

    const safeBots = Array.isArray(bots) ? bots : [];
    const safeChannels = Array.isArray(channels) ? channels : [];
    const safeChats = Array.isArray(chats) ? chats : [];
    const safeForms = Array.isArray(forms) ? forms : [];
    const safeBotChatConnections = Array.isArray(bot2ChatConnections) ? bot2ChatConnections : [];
    const safeChatChannelConnections = Array.isArray(chat2ChannelConnections) ? chat2ChannelConnections : [];
    const safeBotConnections = Array.isArray(bot2ChannelConnections) ? bot2ChannelConnections : [];
    const safeFormConnections = Array.isArray(form2ChannelConnections) ? form2ChannelConnections : [];

    return (
        <>
            <h1>{wp.i18n.__( 'CF7 VK settings', 'cf7-vk' )}</h1>
            <p className="screen-description">
                {wp.i18n.__(
                    'The VK transport backend is now wired: you can verify community credentials here, while Long Poll chat discovery and linking remain the next milestone.',
                    'cf7-vk'
                )}
            </p>

            <div className="cf7vk-layout">
                <div className="cf7vk-sidebar">
                    <Settings />
                    <NewBot onCreated={loadData} />
                    <NewChannel onCreated={loadData} />
                </div>

                <div className="cf7vk-main">
                    <section className="cf7vk-column">
                        <h2>{wp.i18n.__( 'VK bots', 'cf7-vk' )}</h2>
                        {safeBots.length === 0 ? (
                            <div className="empty-state">
                                {wp.i18n.__( 'No VK bot connections yet.', 'cf7-vk' )}
                            </div>
                        ) : safeBots.map((bot) => (
                            <Bot
                                key={bot.id}
                                bot={bot}
                                chats={safeChats}
                                bot2ChatConnections={safeBotChatConnections}
                                onUpdated={loadData}
                            />
                        ))}
                    </section>

                    <section className="cf7vk-column">
                        <h2>{wp.i18n.__( 'Channels', 'cf7-vk' )}</h2>
                        {safeChannels.length === 0 ? (
                            <div className="empty-state">
                                {wp.i18n.__( 'No routing channels yet.', 'cf7-vk' )}
                            </div>
                        ) : safeChannels.map((channel) => (
                            <Channel
                                key={channel.id}
                                channel={channel}
                                bots={safeBots}
                                chats={safeChats}
                                forms={safeForms}
                                bot2ChatConnections={safeBotChatConnections}
                                chat2ChannelConnections={safeChatChannelConnections}
                                bot2ChannelConnections={safeBotConnections}
                                form2ChannelConnections={safeFormConnections}
                                onUpdated={loadData}
                            />
                        ))}
                    </section>
                </div>
            </div>
        </>
    );
};

export default App;
