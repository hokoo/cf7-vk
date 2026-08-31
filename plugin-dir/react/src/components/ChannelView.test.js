import {render, screen} from '@testing-library/react';
import ChannelView from './ChannelView';

const baseProps = {
    channel: {id: 20},
    titleValue: 'Channel',
    saving: false,
    error: null,
    handleTitleChange: jest.fn(),
    handleKeyDown: jest.fn(),
    saveTitle: jest.fn(),
    botForChannel: null,
    renderedChats: [],
    formsForChannel: [],
    availableForms: [],
    showFormSelector: false,
    handleAddForm: jest.fn(),
    handleFormSelect: jest.fn(),
    handleRemoveForm: jest.fn(),
    availableBots: [],
    handleBotSelect: jest.fn(),
    handleRemoveBot: jest.fn(),
    handleToggleChat: jest.fn(),
    deleteChannel: jest.fn(),
    getToggleButtonLabel: jest.fn(),
    renderChannelClasses: jest.fn(() => ''),
    dataAvailability: {forms: 'ready', bots: 'ready', chats: 'ready'}
};

const renderChannelView = (props = {}) => render(<ChannelView {...baseProps} {...props} />);

beforeEach(() => {
    global.wp = {
        i18n: {
            __: (value) => value
        }
    };
});

test('renders stable channel selectors for browser smoke', () => {
    renderChannelView();

    expect(screen.getByTestId('cf7vk-channel-20')).toBeInTheDocument();
    expect(screen.getByTestId('cf7vk-channel-title-input-20')).toHaveValue('Channel');
    expect(screen.getByTestId('cf7vk-channel-20-add-form')).toBeEnabled();
    expect(screen.getByTestId('cf7vk-remove-channel-20')).toBeEnabled();
});

test('disables form controls when form data is unavailable', () => {
    renderChannelView({dataAvailability: {forms: 'error', bots: 'ready', chats: 'ready'}});

    expect(screen.getByTestId('cf7vk-channel-20-add-form')).toBeDisabled();
    expect(screen.getByText('Forms are unavailable.')).toBeInTheDocument();
});

test('renders stable relation selectors when data is ready', () => {
    renderChannelView({
        botForChannel: {id: 10, title: 'VK Bot', statusClass: 'online'},
        renderedChats: [{id: 30, title: 'Dialog', status: 'Active'}],
        formsForChannel: [{id: 40, title: 'Form'}],
    });

    expect(screen.getByTestId('cf7vk-channel-20-remove-bot')).toHaveAttribute('aria-label', 'Remove bot from channel');
    expect(screen.getByTestId('cf7vk-channel-20-chat-30')).toBeInTheDocument();
    expect(screen.getByTestId('cf7vk-channel-20-form-40')).toBeInTheDocument();
    expect(screen.getByTestId('cf7vk-channel-20-remove-form-40')).toHaveAttribute('aria-label', 'Remove form from channel');
});
