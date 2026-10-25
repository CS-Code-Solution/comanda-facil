import { router, usePage, usePoll } from '@inertiajs/react';
import Pusher from 'pusher-js';
import { useEffect, useState } from 'react';

type Realtime = {
    key: string | null;
    host: string;
    port: number;
    scheme: string;
};

export function useOperationsRealtime(
    channel: string,
    authUrl = '/broadcasting/auth',
) {
    const { realtime } = usePage<{ realtime: Realtime }>().props;
    const [connected, setConnected] = useState(false);
    usePoll(15000);
    useEffect(() => {
        if (!realtime.key) return;
        let refreshing = false;
        let pending = false;
        let disposed = false;
        const refresh = () => {
            if (disposed) return;
            if (refreshing) {
                pending = true;
                return;
            }
            refreshing = true;
            router.reload({
                onFinish: () => {
                    refreshing = false;
                    if (pending) {
                        pending = false;
                        refresh();
                    }
                },
            });
        };
        const client = new Pusher(realtime.key, {
            cluster: 'local',
            wsHost: realtime.host,
            wsPort: realtime.port,
            wssPort: realtime.port,
            forceTLS: realtime.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
            channelAuthorization: {
                endpoint: authUrl,
                transport: 'ajax',
                headersProvider: () => ({
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                            ?.slice(11) ?? '',
                    ),
                    Accept: 'application/json',
                }),
            },
        });
        const subscription = client.subscribe('private-' + channel);
        subscription.bind('operations.changed', refresh);
        subscription.bind('pusher:subscription_succeeded', () => {
            setConnected(true);
            refresh();
        });
        subscription.bind('pusher:subscription_error', () =>
            setConnected(false),
        );
        client.connection.bind(
            'state_change',
            ({ current }: { current: string }) => {
                if (current !== 'connected') setConnected(false);
            },
        );
        return () => {
            disposed = true;
            client.disconnect();
        };
    }, [
        channel,
        authUrl,
        realtime.key,
        realtime.host,
        realtime.port,
        realtime.scheme,
    ]);
    return connected
        ? 'Atualização em tempo real conectada'
        : 'Reconectando; consulta automática a cada 15 segundos';
}
