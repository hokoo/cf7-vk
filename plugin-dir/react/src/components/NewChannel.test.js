import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import NewChannel from './NewChannel';
import {apiCreateChannel} from '../utils/api';

jest.mock('../utils/api', () => ({
    apiCreateChannel: jest.fn()
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

test('does not create a channel while the collection is unavailable', () => {
    render(<NewChannel onCreated={jest.fn()} disabled />);

    fireEvent.click(screen.getByTestId('cf7vk-create-channel'));

    expect(apiCreateChannel).not.toHaveBeenCalled();
});

test('disables creation while the channel request is in flight and reports success', async () => {
    const creation = createDeferred();
    const onCreated = jest.fn();
    apiCreateChannel.mockReturnValueOnce(creation.promise);

    render(<NewChannel onCreated={onCreated} />);

    fireEvent.click(screen.getByTestId('cf7vk-create-channel'));

    expect(screen.getByTestId('cf7vk-create-channel')).toBeDisabled();
    expect(apiCreateChannel).toHaveBeenCalledWith('Channel');

    await act(async () => {
        creation.resolve({id: 20});
        await Promise.resolve();
    });

    expect(onCreated).toHaveBeenCalledWith({id: 20});
    expect(screen.getByTestId('cf7vk-create-channel')).toBeEnabled();
});

test('re-enables creation and shows an alert when channel creation fails', async () => {
    const alert = jest.spyOn(window, 'alert').mockImplementation(() => {});
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    apiCreateChannel.mockRejectedValueOnce(new Error('create failed'));

    render(<NewChannel onCreated={jest.fn()} />);

    fireEvent.click(screen.getByTestId('cf7vk-create-channel'));

    await waitFor(() => {
        expect(alert).toHaveBeenCalledWith('Failed to create channel');
        expect(screen.getByTestId('cf7vk-create-channel')).toBeEnabled();
    });

    alert.mockRestore();
    consoleError.mockRestore();
});
