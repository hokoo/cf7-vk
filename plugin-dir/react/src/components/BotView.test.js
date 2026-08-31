import {render, screen} from '@testing-library/react';
import BotView from './BotView';

const baseProps = {
    bot: {
        id: 1,
        title: {rendered: 'VK Bot'},
        isAccessTokenDefinedByConst: false,
        isAccessTokenEmpty: false,
        accessTokenConst: 'CF7VK_ACCESS_TOKEN__1',
        groupIdConst: 'CF7VK_GROUP_ID__1'
    },
    form: {
        groupId: '1001',
        accessToken: 'secret-9999',
        authCommand: 'start'
    },
    saving: false,
    feedback: null,
    statusClass: 'online',
    chatsForBot: [],
    bot2ChatConnections: [],
    updateField: () => () => {},
    remove: jest.fn(),
    handleFieldBlur: jest.fn(),
    handleToggleChatStatus: jest.fn(),
    handleActivatePendingChat: jest.fn(),
    disconnectChat: jest.fn(),
    hasConfiguredBot: true,
    isEditingToken: false,
    isEditingCommand: false,
    startEditingToken: jest.fn(),
    startEditingCommand: jest.fn(),
    commitInlineEdit: jest.fn(),
    handleInlineEditorKeyDown: () => () => {}
};

const renderBotView = (props = {}) => render(<BotView {...baseProps} {...props} />);

beforeEach(() => {
    global.wp = {
        i18n: {
            __: (value) => value
        }
    };
    global.cf7vkData = {
        phrases: {
            emptySecret: '[empty]'
        }
    };
});

test('renders stable bot selectors for browser smoke', () => {
    renderBotView();

    expect(screen.getByTestId('cf7vk-bot-1')).toBeInTheDocument();
    expect(screen.getByTestId('cf7vk-bot-token-display-1')).toBeInTheDocument();
    expect(screen.getByTestId('cf7vk-bot-group-id-1')).toHaveValue('1001');
    expect(screen.getByTestId('cf7vk-remove-bot-1')).toBeEnabled();
});

test('renders stable chat selectors when dialog data is ready', () => {
    renderBotView({
        chatsForBot: [{id: 30, title: {rendered: 'Dialog'}}],
        bot2ChatConnections: [{data: {from: 1, to: 30, meta: {status: ['active']}}}]
    });

    expect(screen.getByTestId('cf7vk-bot-1-chat-30')).toBeInTheDocument();
    expect(screen.getByTestId('cf7vk-bot-1-chat-30-toggle')).toHaveTextContent('Mute');
    expect(screen.getByTestId('cf7vk-bot-1-chat-30-remove')).toHaveTextContent('Remove');
});

test('shows a targeted message when dialog data is unavailable', () => {
    renderBotView({chatDataStatus: 'error'});

    expect(screen.getByText('VK dialogs could not be loaded.')).toBeInTheDocument();
});
