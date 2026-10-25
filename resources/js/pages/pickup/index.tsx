import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import { Pagination } from '@/pages/dining/index';
type Props = {
    orders: {
        reference: string;
        locationLabel: string;
        status: string;
        phone: string;
        url: string;
        cashUrl: string | null;
    }[];
    pagination: { previous: string | null; next: string | null };
    filter: string;
    receiving: boolean;
    canManage: boolean;
    serviceUrl: string;
    menuUrl: string | null;
};
export default function Pickup({
    orders,
    pagination,
    filter,
    receiving,
    canManage,
    serviceUrl,
    menuUrl,
}: Props) {
    const connection = useOperationsRealtime('operations.pickup');
    const service = useForm({ open: !receiving, reason: '' });
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Retiradas" />
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-semibold">Retiradas</h1>
                    <p className="mt-2" role="status">
                        {connection}
                    </p>
                    <p>Recebimento {receiving ? 'aberto' : 'pausado'}</p>
                </div>
                {menuUrl && (
                    <Button asChild>
                        <Link href={menuUrl}>Novo pedido de retirada</Link>
                    </Button>
                )}
            </header>
            {canManage && (
                <form
                    className="max-w-lg space-y-3 rounded border p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        service.transform((data) => ({
                            ...data,
                            open: !receiving,
                        }));
                        service.post(serviceUrl, {
                            onSuccess: () => service.reset('reason'),
                        });
                    }}
                >
                    <label htmlFor="receiving-reason">
                        Motivo para {receiving ? 'pausar' : 'abrir'} recebimento
                    </label>
                    <Input
                        id="receiving-reason"
                        required
                        minLength={3}
                        maxLength={500}
                        value={service.data.reason}
                        onChange={(e) =>
                            service.setData('reason', e.target.value)
                        }
                    />
                    {Object.entries(service.errors).map(([k, v]) => (
                        <InputError key={k} message={v} />
                    ))}
                    <Button variant="outline" disabled={service.processing}>
                        {receiving ? 'Pausar' : 'Abrir'} recebimento de retirada
                    </Button>
                </form>
            )}
            <form method="get" className="flex flex-wrap items-center gap-3">
                <label htmlFor="pickup-status">Estado</label>
                <select
                    className="rounded border bg-background p-2"
                    id="pickup-status"
                    name="status"
                    defaultValue={filter}
                >
                    <option value="">Em andamento</option>
                    <option value="confirmed">Confirmado</option>
                    <option value="preparing">Em preparo</option>
                    <option value="ready">Pronto para retirada</option>
                    <option value="collected">Retirado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
                <Button variant="outline">Filtrar</Button>
            </form>
            {orders.length === 0 && <p>Nenhum pedido nesta fila.</p>}
            <div className="grid gap-4 md:grid-cols-2">
                {orders.map((order) => (
                    <Card key={order.reference}>
                        <CardContent className="space-y-3 pt-6">
                            <h2 className="font-semibold">
                                {order.locationLabel} · {order.status}
                            </h2>
                            <p>Contato: {order.phone}</p>
                            <p className="text-xs break-all">
                                {order.reference}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button asChild variant="outline">
                                    <Link href={order.url}>
                                        Acompanhar pedido
                                    </Link>
                                </Button>
                                {order.cashUrl && (
                                    <Button asChild variant="outline">
                                        <Link href={order.cashUrl}>
                                            Abrir conta
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
            <Pagination links={pagination} />
        </div>
    );
}
