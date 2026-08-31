import {act, fireEvent, render, screen} from '@testing-library/react';
import Bot from './Bot';
import {
    apiFetchUpdates,
    apiPingBot,
    apiSaveBot,
    apiSaveBotCredentials
} from '../utils/api';

jest.mock('../utils/api', () => ({
    apiActivateBotChat: jest.fn(),
    apiDeleteBot: jest.fn(),
    apiDisconnectBotFromChat: jest.fn(),
    apiFetchUpdates: jest.fn(),
    apiPingBot: jest.fn(),
    apiSaveBot: jest.fn(),
    apiSaveBotCredentials: jest.fn(),
    apiSetBotChatStatus: jest.fn()
}));

const baseBot = {
    id: 1,
    title: {rendered: 'VK Bot'},
    groupId: '',
    accessToken: '',
    authCommand: 'start',
    lastStatus: 'unknown',
    isAccessTokenEmpty: true,
    isAccessTokenDefinedByConst: false,
    isGroupIdDefinedByConst: false,
    accessTokenConst: 'CF7VK_ACCESS_TOKEN__1',
    groupIdConst: 'CF7VK_GROUP_ID__1'
};

const renderBot = (overrides = {}, props = {}) => render(
    <Bot
        bot={{...baseBot, ...overrides}}
        chats={[]}
        bot2ChatConnections={[]}
        onBotSaved={jest.fn()}
        onBotRemoved={jest.fn()}
        refreshBots={jest.fn().mockResolvedValue(undefined)}
        refreshBotRuntime={jest.fn().mockResolvedValue(undefined)}
        refreshBotChatConnections={jest.fn().mockResolvedValue(undefined)}
        refreshBotChannelConnections={jest.fn().mockResolvedValue(undefined)}
        refreshChatChannelConnections={jest.fn().mockResolvedValue(undefined)}
        {...props}
    />
);

const flushTimersAndPromises = async () => {
    await act(async () => {
        jest.runOnlyPendingTimers();
        await Promise.resolve();
    });
};

beforeEach(() => {
    jest.useFakeTimers();
    jest.clearAllMocks();
    apiFetchUpdates.mockResolvedValue({updates: [], hasNewChats: false, hasNewConnections: false});
    apiPingBot.mockResolvedValue({longPollReady: false});
});

afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
});

test('shows credential validation failure without saving stale bot state', async () => {
    const error = new Error('Invalid VK token');
    error.status = 502;
    apiSaveBotCredentials.mockRejectedValue(error);
    const onBotSaved = jest.fn();
    const {container} = renderBot({}, {onBotSaved});

    fireEvent.change(screen.getByLabelText('Group ID'), {target: {value: '1001'}});
    fireEvent.click(container.querySelector('.show-token'));
    fireEvent.change(container.querySelector('.edit-token'), {target: {value: 'bad-token'}});
    fireEvent.blur(container.querySelector('.edit-token'));

    await flushTimersAndPromises();

    expect(apiSaveBotCredentials).toHaveBeenCalledTimes(1);
    expect(apiSaveBotCredentials).toHaveBeenCalledWith(1, {
        groupId: '1001',
        accessToken: 'bad-token',
        authCommand: 'start'
    });
    expect(apiSaveBot).not.toHaveBeenCalled();
    expect(onBotSaved).not.toHaveBeenCalled();
    expect(await screen.findByText('Invalid VK token')).toBeInTheDocument();
});

test('successful credential save uses persisted response and refreshes reset relations', async () => {
    const savedBot = {
        ...baseBot,
        groupId: '2002',
        accessToken: 'new-token',
        title: {rendered: 'New Community'},
        lastStatus: 'online',
        isAccessTokenEmpty: false,
        longPollServer: 'https://lp.new.test',
        longPollTs: '55'
    };
    apiSaveBotCredentials.mockResolvedValue({
        bot: savedBot,
        longPollReady: true,
        identityChanged: true,
        relationsReset: 2
    });
    const onBotSaved = jest.fn();
    const refreshBots = jest.fn().mockResolvedValue(undefined);
    const refreshBotChatConnections = jest.fn().mockResolvedValue(undefined);
    const refreshBotChannelConnections = jest.fn().mockResolvedValue(undefined);
    const {container} = renderBot(
        {},
        {
            onBotSaved,
            refreshBots,
            refreshBotChatConnections,
            refreshBotChannelConnections
        }
    );

    fireEvent.change(screen.getByLabelText('Group ID'), {target: {value: '2002'}});
    fireEvent.click(container.querySelector('.show-token'));
    fireEvent.change(container.querySelector('.edit-token'), {target: {value: 'new-token'}});
    fireEvent.blur(container.querySelector('.edit-token'));

    await flushTimersAndPromises();

    expect(apiSaveBotCredentials).toHaveBeenCalledTimes(1);
    expect(apiSaveBot).not.toHaveBeenCalled();
    expect(apiPingBot).not.toHaveBeenCalled();
    expect(onBotSaved).toHaveBeenCalledWith(savedBot);
    expect(refreshBotChatConnections).toHaveBeenCalledTimes(1);
    expect(refreshBotChannelConnections).toHaveBeenCalledTimes(1);
    expect(refreshBots).toHaveBeenCalledTimes(1);
});

test('polling displays structured transient fetch errors', async () => {
    apiPingBot.mockResolvedValue({longPollReady: true});
    apiFetchUpdates.mockResolvedValue({
        locked: false,
        transientError: true,
        error: {
            message: 'VK timeout'
        }
    });
    const refreshBots = jest.fn().mockResolvedValue(undefined);
    renderBot(
        {
            groupId: '1001',
            accessToken: 'old-token',
            isAccessTokenEmpty: false,
            lastStatus: 'online'
        },
        {refreshBots}
    );

    await act(async () => {
        await Promise.resolve();
    });
    await flushTimersAndPromises();

    expect(await screen.findByText('VK timeout')).toBeInTheDocument();
    expect(refreshBots).toHaveBeenCalled();
});
