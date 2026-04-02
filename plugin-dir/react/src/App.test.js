import {render, screen} from '@testing-library/react';
import App from './App';

jest.mock('./components/Settings', () => () => <div>Settings component</div>);
jest.mock('./components/NewBot', () => () => <div>New bot component</div>);
jest.mock('./components/NewChannel', () => () => <div>New channel component</div>);
jest.mock('./components/Bot', () => () => <div>Bot component</div>);
jest.mock('./components/Channel', () => () => <div>Channel component</div>);
jest.mock('./utils/api', () => ({
    fetchBots: jest.fn().mockResolvedValue([]),
    fetchChats: jest.fn().mockResolvedValue([]),
    fetchChannels: jest.fn().mockResolvedValue([]),
    fetchForms: jest.fn().mockResolvedValue([]),
    fetchBotsForChats: jest.fn().mockResolvedValue([]),
    fetchChatsForChannels: jest.fn().mockResolvedValue([]),
    fetchBotsForChannels: jest.fn().mockResolvedValue([]),
    fetchFormsForChannels: jest.fn().mockResolvedValue([])
}));

beforeEach(() => {
    global.wp = {
        i18n: {
            __: (value) => value
        }
    };
});

test('renders shell heading', async () => {
    render(<App />);

    expect(await screen.findByText('VK notificator settings')).toBeInTheDocument();
});
