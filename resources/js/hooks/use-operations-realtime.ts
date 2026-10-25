import { router, usePage, usePoll } from '@inertiajs/react';
import Pusher from 'pusher-js';
import { useEffect, useRef, useState } from 'react';

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
    const writing = useRef(false);
    usePoll(15000, { onBefore: () => !writing.current });
    useEffect(() => {
        if (!realtime.key) return;
        let refreshing = false;
        let pending = false;
        let disposed = false;
        const refresh = () => {
            if (disposed) return;
            if (writing.current) {
                pending = true;
                return;
            }
            if (refreshing) {
                pending = true;
                return;
            }
            refreshing = true;
            router.reload({
                preserveErrors: true,
                except: ['errors'],
                onFinish: () => {
                    refreshing = false;
                    if (pending) {
                        pending = false;
                        refresh();
                    }
                },
            });
        };
        const stopStart = router.on('start', (event) => {
            if (event.detail.visit.method !== 'get') writing.current = true;
        });
        const stopFinish = router.on('finish', (event) => {
            if (event.detail.visit.method === 'get') return;
            writing.current = false;
            if (pending) {
                pending = false;
                // Run after the form has handled its response and validation errors.
                setTimeout(refresh, 0);
            }
        });
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
            stopStart();
            stopFinish();
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
