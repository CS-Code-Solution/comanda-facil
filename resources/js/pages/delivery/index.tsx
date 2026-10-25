import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import { Pagination } from '@/pages/dining/index';

export const deliveryStatuses = [
    ['confirmed', 'Confirmado'],
    ['preparing', 'Em preparo'],
    ['ready', 'Pronto'],
    ['waiting_courier', 'Aguardando entregador'],
    ['dispatched', 'Saiu para entrega'],
    ['delivered', 'Entregue'],
    ['cancelled', 'Cancelado'],
];

export default function DeliveryIndex({
    orders,
    pagination,
    filter,
    receiving,
    adminUrl,
    menuUrl,
    all,
}: {
    orders: { id: number; label: string; status: string; url: string }[];
    pagination: { previous: string | null; next: string | null };
    filter: string;
    receiving: boolean;
    adminUrl: string | null;
    menuUrl: string | null;
    all: boolean;
}) {
    const connection = useOperationsRealtime('operations.delivery');
    return (
        <main className="space-y-6 p-4 md:p-6">
            <Head title="Entregas" />
            <header className="flex flex-wrap justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold">
                        {all ? 'Entregas' : 'Minhas entregas'}
                    </h1>
                    <p role="status" className="mt-2 text-sm">
                        {connection}
                    </p>
                    <p>Recebimento {receiving ? 'aberto' : 'pausado'}</p>
                </div>
                <div className="flex flex-wrap gap-3">
                    {adminUrl && (
                        <Button asChild variant="outline">
                            <Link href={adminUrl}>
                                Cobertura, taxas e mínimo
                            </Link>
                        </Button>
                    )}
                    {menuUrl && (
                        <Button asChild>
                            <Link href={menuUrl}>Novo pedido de entrega</Link>
                        </Button>
                    )}
                </div>
            </header>
            <form method="get" className="flex flex-wrap items-center gap-3">
                <label htmlFor="delivery-status">Estado</label>
                <select
                    id="delivery-status"
                    name="status"
                    defaultValue={filter}
                    className="rounded border bg-background p-2"
                >
                    <option value="">Em andamento</option>
                    {deliveryStatuses.map(([value, label]) => (
                        <option value={value} key={value}>
                            {label}
                        </option>
                    ))}
                </select>
                <Button variant="outline">Filtrar</Button>
            </form>
            {!orders.length && <p>Nenhuma entrega nesta fila.</p>}
            <div className="grid gap-3 md:grid-cols-2">
                {orders.map((order) => (
                    <Card key={order.id}>
                        <CardContent className="flex flex-wrap justify-between gap-3 pt-6">
                            <div>
                                <h2 className="font-semibold">{order.label}</h2>
                                <p>{order.status}</p>
                            </div>
                            <Button asChild variant="outline">
                                <Link href={order.url}>Abrir entrega</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ))}
            </div>
            <Pagination links={pagination} />
        </main>
    );
}
