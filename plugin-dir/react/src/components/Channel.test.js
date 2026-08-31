import {act, render} from '@testing-library/react';
import Channel from './Channel';
import {
    apiConnectFormToChannel,
    apiDeleteChannel,
} from '../utils/api';

let mockChannelViewProps;

jest.mock('./ChannelView', () => (props) => {
    mockChannelViewProps = props;
    return null;
});

jest.mock('../utils/api', () => ({
    apiConnectBotToChannel: jest.fn(),
    apiConnectChatToChannel: jest.fn(),
    apiConnectFormToChannel: jest.fn(),
    apiDeleteChannel: jest.fn(),
    apiDisconnectBotFromChannel: jest.fn(),
    apiDisconnectChatFromChannel: jest.fn(),
    apiDisconnectFormFromChannel: jest.fn(),
    apiSaveChannel: jest.fn()
}));

const renderChannel = (props = {}) => render(
    <Channel
        channel={{id: 20, title: {rendered: 'Channel'}}}
        bots={[]}
        chats={[]}
        forms={[]}
        bot2ChatConnections={[]}
        chat2ChannelConnections={[]}
        bot2ChannelConnections={[]}
        form2ChannelConnections={[]}
        onChannelSaved={jest.fn()}
        onChannelRemoved={jest.fn()}
        refreshBotChannelConnections={jest.fn().mockResolvedValue(undefined)}
        refreshChatChannelConnections={jest.fn().mockResolvedValue(undefined)}
        refreshFormChannelConnections={jest.fn().mockResolvedValue(undefined)}
        {...props}
    />
);

beforeEach(() => {
    jest.clearAllMocks();
    mockChannelViewProps = null;
    apiConnectFormToChannel.mockResolvedValue({});
    apiDeleteChannel.mockResolvedValue({});

    global.wp = {
        i18n: {
            __: (value) => value
        }
    };
});

test('deletes a channel only after confirmation and REST success', async () => {
    const confirm = jest.spyOn(window, 'confirm').mockReturnValue(true);
    const onChannelRemoved = jest.fn();

    renderChannel({onChannelRemoved});

    await act(async () => {
        await mockChannelViewProps.deleteChannel();
    });

    expect(apiDeleteChannel).toHaveBeenCalledWith(20);
    expect(onChannelRemoved).toHaveBeenCalledWith(20);

    confirm.mockRestore();
});

test('failed channel deletion keeps local state and exposes an error', async () => {
    const confirm = jest.spyOn(window, 'confirm').mockReturnValue(true);
    const onChannelRemoved = jest.fn();
    apiDeleteChannel.mockRejectedValueOnce(new Error('delete failed'));

    renderChannel({onChannelRemoved});

    await act(async () => {
        await mockChannelViewProps.deleteChannel();
    });

    expect(onChannelRemoved).not.toHaveBeenCalled();
    expect(mockChannelViewProps.saving).toBe(false);
    expect(mockChannelViewProps.error).toBe('Failed to remove channel');

    confirm.mockRestore();
});

test('failed form relation mutation does not refresh stale successful state', async () => {
    const refreshFormChannelConnections = jest.fn().mockResolvedValue(undefined);
    apiConnectFormToChannel.mockRejectedValueOnce(new Error('connect failed'));

    renderChannel({refreshFormChannelConnections});

    await act(async () => {
        await mockChannelViewProps.handleFormSelect({value: 40});
    });

    expect(apiConnectFormToChannel).toHaveBeenCalledWith(40, 20);
    expect(refreshFormChannelConnections).not.toHaveBeenCalled();
    expect(mockChannelViewProps.error).toBe('Failed to assign form');
    expect(mockChannelViewProps.showFormSelector).toBe(false);
});
