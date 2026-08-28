import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            const token = window.sessionStorage.getItem('poptalk.radio-session-token');
            const headers = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };

            if (token) {
                headers.Authorization = `Bearer ${token}`;
            }

            fetch(import.meta.env.VITE_BROADCAST_AUTH_ENDPOINT ?? '/api/broadcasting/auth', {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: channel.name,
                }),
            })
                .then(async (response) => {
                    if (! response.ok) {
                        throw new Error(`Broadcast authorization failed with ${response.status}.`);
                    }

                    return response.json();
                })
                .then((data) => callback(null, data))
                .catch((error) => callback(error, null));
        },
    }),
});
