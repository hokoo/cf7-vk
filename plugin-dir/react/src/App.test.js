import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import App, {SettingsErrorBoundary} from './App';
import {
    fetchBots,
    fetchBotsForChannels,
    fetchBotsForChats,
    fetchChannels,
    fetchChats,
    fetchChatsForChannels,
    fetchForms,
    fetchFormsForChannels,
} from './utils/api';

jest.mock('./components/NewBot', () => ({disabled = false}) => (
    <button type="button" disabled={disabled}>Create Bot</button>
));
jest.mock('./components/NewChannel', () => ({disabled = false}) => (
    <button type="button" disabled={disabled}>Create Channel</button>
));
jest.mock('./components/Bot', () => ({bot, chatDataStatus}) => (
    <div>
        Bot {bot.id}
        <span data-testid={`bot-${bot.id}-chats-state`}>{chatDataStatus}</span>
    </div>
));
jest.mock('./components/Channel', () => ({
    channel,
    dataAvailability,
    bot2ChannelConnections,
    chat2ChannelConnections,
    form2ChannelConnections,
    onChannelRemoved
}) => (
    <div>
        Channel {channel.id}
        <span data-testid={`channel-${channel.id}-forms-state`}>{dataAvailability.forms}</span>
        <span data-testid={`channel-${channel.id}-bots-state`}>{dataAvailability.bots}</span>
        <span data-testid={`channel-${channel.id}-chats-state`}>{dataAvailability.chats}</span>
        <span data-testid={`channel-${channel.id}-bot-relations`}>
            {bot2ChannelConnections.map((item) => item?.data?.to).join(',')}
        </span>
        <span data-testid={`channel-${channel.id}-chat-relations`}>
            {chat2ChannelConnections.map((item) => item?.data?.to).join(',')}
        </span>
        <span data-testid={`channel-${channel.id}-form-relations`}>
            {form2ChannelConnections.map((item) => item?.data?.to).join(',')}
        </span>
        <button type="button" disabled={'ready' !== dataAvailability.forms}>
            Add Form {channel.id}
        </button>
        <button type="button" onClick={() => onChannelRemoved(channel.id)}>
            Remove Channel {channel.id}
        </button>
    </div>
));
jest.mock('./utils/api', () => ({
    fetchBots: jest.fn(),
    fetchChats: jest.fn(),
    fetchChannels: jest.fn(),
    fetchForms: jest.fn(),
    fetchBotsForChats: jest.fn(),
    fetchChatsForChannels: jest.fn(),
    fetchBotsForChannels: jest.fn(),
    fetchFormsForChannels: jest.fn()
}));

const createDeferred = () => {
    let resolve;
    const promise = new Promise((res) => {
        resolve = res;
    });

    return {promise, resolve};
};

const setDefaultApiResponses = () => {
    fetchBots.mockResolvedValue([{id: 10, title: {rendered: 'Bot'}}]);
    fetchChannels.mockResolvedValue([{id: 20, title: {rendered: 'Channel'}}]);
    fetchChats.mockResolvedValue([{id: 30, title: {rendered: 'Dialog'}}]);
    fetchForms.mockResolvedValue([{id: 40, title: 'Form'}]);
    fetchBotsForChats.mockResolvedValue([]);
    fetchChatsForChannels.mockResolvedValue([]);
    fetchBotsForChannels.mockResolvedValue([]);
    fetchFormsForChannels.mockResolvedValue([]);
};

beforeEach(() => {
    jest.clearAllMocks();

    global.wp = {
        i18n: {
            __: (value) => value
        }
    };

    setDefaultApiResponses();
});

test('shows loading until at least one settings resource settles', async () => {
    const deferreds = [
        fetchBots,
        fetchChannels,
        fetchChats,
        fetchForms,
        fetchBotsForChats,
        fetchChatsForChannels,
        fetchBotsForChannels,
        fetchFormsForChannels,
    ].map((loader) => {
        const deferred = createDeferred();
        loader.mockReturnValueOnce(deferred.promise);
        return deferred;
    });

    render(<App />);

    expect(screen.getByText('Loading data...')).toBeInTheDocument();

    await act(async () => {
        deferreds.forEach((deferred) => deferred.resolve([]));
        await Promise.resolve();
    });

    expect(await screen.findByText('VK Message Bridge Settings')).toBeInTheDocument();
});

test('keeps successful resources visible and retries only failed requests', async () => {
    fetchChannels.mockRejectedValueOnce(new Error('channels endpoint failed'));

    render(<App />);

    expect(await screen.findByText('Bot 10')).toBeInTheDocument();
    expect(screen.getByText('Channels could not be loaded.')).toBeInTheDocument();
    expect(screen.getByText('Some settings data could not be loaded.')).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Create Bot'})).toBeEnabled();
    expect(screen.getByRole('button', {name: 'Create Channel'})).toBeDisabled();

    fireEvent.click(screen.getByText('Retry failed requests'));

    await waitFor(() => {
        expect(fetchChannels).toHaveBeenCalledTimes(2);
        expect(screen.queryByText('Some settings data could not be loaded.')).not.toBeInTheDocument();
        expect(screen.getByText('Channel 20')).toBeInTheDocument();
    });

    expect(fetchBots).toHaveBeenCalledTimes(1);
    expect(fetchChats).toHaveBeenCalledTimes(1);
    expect(fetchForms).toHaveBeenCalledTimes(1);
});

test('marks channel form controls unavailable when forms fail', async () => {
    fetchForms.mockRejectedValueOnce(new Error('forms endpoint failed'));

    render(<App />);

    expect(await screen.findByText('Channel 20')).toBeInTheDocument();
    expect(screen.getByTestId('channel-20-forms-state')).toHaveTextContent('error');
    expect(screen.getByRole('button', {name: 'Add Form 20'})).toBeDisabled();
    expect(screen.getByText('Some settings data could not be loaded.')).toBeInTheDocument();
});

test('marks bot chat data unavailable when chat relations fail', async () => {
    fetchBotsForChats.mockRejectedValueOnce(new Error('bot chat relation endpoint failed'));

    render(<App />);

    expect(await screen.findByText('Bot 10')).toBeInTheDocument();
    expect(screen.getByTestId('bot-10-chats-state')).toHaveTextContent('error');
    expect(screen.getByTestId('channel-20-chats-state')).toHaveTextContent('error');
});

test('recovers targeted resource state after retry succeeds', async () => {
    fetchForms.mockRejectedValueOnce(new Error('forms endpoint failed'));

    render(<App />);

    expect(await screen.findByText('Channel 20')).toBeInTheDocument();
    expect(screen.getByTestId('channel-20-forms-state')).toHaveTextContent('error');

    fireEvent.click(screen.getByText('Retry failed requests'));

    await waitFor(() => {
        expect(fetchForms).toHaveBeenCalledTimes(2);
        expect(screen.getByTestId('channel-20-forms-state')).toHaveTextContent('ready');
        expect(screen.queryByText('Some settings data could not be loaded.')).not.toBeInTheDocument();
    });

    expect(fetchChannels).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('button', {name: 'Add Form 20'})).toBeEnabled();
});

test('cleans local channel relations after a confirmed channel removal', async () => {
    fetchChannels.mockResolvedValue([
        {id: 20, title: {rendered: 'Channel 20'}},
        {id: 21, title: {rendered: 'Channel 21'}},
    ]);
    fetchBotsForChannels.mockResolvedValue([
        {data: {id: 201, from: 10, to: 20}},
        {data: {id: 202, from: 10, to: 21}},
    ]);
    fetchChatsForChannels.mockResolvedValue([
        {data: {id: 301, from: 30, to: 20}},
        {data: {id: 302, from: 30, to: 21}},
    ]);
    fetchFormsForChannels.mockResolvedValue([
        {data: {id: 401, from: 40, to: 20}},
        {data: {id: 402, from: 40, to: 21}},
    ]);

    render(<App />);

    expect(await screen.findByText('Channel 20')).toBeInTheDocument();
    expect(screen.getByTestId('channel-21-bot-relations')).toHaveTextContent('20,21');
    expect(screen.getByTestId('channel-21-chat-relations')).toHaveTextContent('20,21');
    expect(screen.getByTestId('channel-21-form-relations')).toHaveTextContent('20,21');

    fireEvent.click(screen.getByText('Remove Channel 20'));

    await waitFor(() => {
        expect(screen.queryByText('Channel 20')).not.toBeInTheDocument();
        expect(screen.getByTestId('channel-21-bot-relations')).toHaveTextContent('21');
        expect(screen.getByTestId('channel-21-chat-relations')).toHaveTextContent('21');
        expect(screen.getByTestId('channel-21-form-relations')).toHaveTextContent('21');
    });
});

test('contains render failures and exposes a retry action', () => {
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    let shouldThrow = true;
    const FlakySettings = () => {
        if (shouldThrow) {
            throw new Error('render failed');
        }

        return <div>Recovered settings</div>;
    };

    render(
        <SettingsErrorBoundary>
            <FlakySettings />
        </SettingsErrorBoundary>
    );

    expect(screen.getByText('The settings screen could not be displayed.')).toBeInTheDocument();

    shouldThrow = false;
    fireEvent.click(screen.getByText('Try again'));

    expect(screen.getByText('Recovered settings')).toBeInTheDocument();
    expect(consoleError).toHaveBeenCalledWith('CF7 VK settings failed to render.');

    consoleError.mockRestore();
});
