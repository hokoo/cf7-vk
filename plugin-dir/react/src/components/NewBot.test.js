import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import NewBot from './NewBot';
import {apiCreateBot} from '../utils/api';

jest.mock('../utils/api', () => ({
    apiCreateBot: jest.fn()
}));

const createDeferred = () => {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return {promise, resolve, reject};
};

beforeEach(() => {
    jest.clearAllMocks();

    global.wp = {
        i18n: {
            __: (value) => value
        }
    };
});

test('does not create a bot while the collection is unavailable', () => {
    render(<NewBot onCreated={jest.fn()} disabled />);

    fireEvent.click(screen.getByTestId('cf7vk-create-bot'));

    expect(apiCreateBot).not.toHaveBeenCalled();
});

test('disables creation while the bot request is in flight and reports success', async () => {
    const creation = createDeferred();
    const onCreated = jest.fn();
    apiCreateBot.mockReturnValueOnce(creation.promise);

    render(<NewBot onCreated={onCreated} />);

    fireEvent.click(screen.getByTestId('cf7vk-create-bot'));

    expect(screen.getByTestId('cf7vk-create-bot')).toBeDisabled();
    expect(apiCreateBot).toHaveBeenCalledWith({
        title: 'VK Bot',
        authCommand: 'start'
    });

    await act(async () => {
        creation.resolve({id: 10});
        await Promise.resolve();
    });

    expect(onCreated).toHaveBeenCalledWith({id: 10});
    expect(screen.getByTestId('cf7vk-create-bot')).toBeEnabled();
});

test('re-enables creation and shows an alert when bot creation fails', async () => {
    const alert = jest.spyOn(window, 'alert').mockImplementation(() => {});
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    apiCreateBot.mockRejectedValueOnce(new Error('create failed'));

    render(<NewBot onCreated={jest.fn()} />);

    fireEvent.click(screen.getByTestId('cf7vk-create-bot'));

    await waitFor(() => {
        expect(alert).toHaveBeenCalledWith('Failed to create bot');
        expect(screen.getByTestId('cf7vk-create-bot')).toBeEnabled();
    });

    alert.mockRestore();
    consoleError.mockRestore();
});
