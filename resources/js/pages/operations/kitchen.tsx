import { Head, Link } from '@inertiajs/react';
import OperationsOrder, {
    type OperationOrder,
} from '@/components/operations-order';
import { Button } from '@/components/ui/button';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import { Pagination } from '@/pages/dining/index';
export default function Kitchen({
    orders,
    pagination,
    filter,
    modality,
    cancellations,
}: {
    orders: OperationOrder[];
    pagination: { previous: string | null; next: string | null };
    filter: string;
    modality: string;
    cancellations: {
        reference: string;
        table: number | null;
        locationLabel: string;
        url: string;
    }[];
}) {
    const connection = useOperationsRealtime('operations.kitchen');
    return (
        <main className="space-y-6 p-4 md:p-6">
            <Head title="Cozinha" />
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold">Cozinha</h1>
                    <p className="mt-2 text-sm" role="status">
                        {connection}
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href="/impressoes">Comprovantes da cozinha</Link>
                </Button>
            </header>
            <form method="get" className="flex flex-wrap items-center gap-3">
                <label htmlFor="status">Estado</label>
                <select
                    id="status"
                    name="status"
                    defaultValue={filter}
                    className="rounded border bg-background p-2"
                >
                    <option value="">Em andamento</option>
                    <option value="confirmed">Confirmado</option>
                    <option value="preparing">Em preparo</option>
                    <option value="ready">Pronto</option>
                    <option value="served">Servido</option>
                    <option value="collected">Retirado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
                <label htmlFor="modality">Modalidade</label>
                <select
                    id="modality"
                    name="modality"
                    defaultValue={modality}
                    className="rounded border bg-background p-2"
                >
                    <option value="">Todas</option>
                    <option value="dining_room">Salão</option>
                    <option value="pickup">Retirada</option>
                </select>
                <Button variant="outline">Filtrar</Button>
            </form>
            {orders.length === 0 && <p>Nenhum pedido nesta fila.</p>}
            {cancellations.length > 0 && (
                <section
                    role="alert"
                    className="space-y-2 rounded border border-destructive p-4"
                >
                    <h2 className="font-semibold">
                        Cancelamentos recentes — interrompa preparo e serviço
                    </h2>
                    <p className="text-sm">
                        Últimos dez nas duas horas anteriores. Confira os
                        comprovantes de cancelamento e o filtro Cancelado para o
                        histórico completo.
                    </p>
                    {cancellations.map((order) => (
                        <p key={order.reference}>
                            <Link className="underline" href={order.url}>
                                {order.locationLabel} · {order.reference}
                            </Link>
                        </p>
                    ))}
                </section>
            )}
            <div className="grid items-start gap-4 lg:grid-cols-2">
                {orders.map((order) => (
                    <OperationsOrder key={order.id} order={order} />
                ))}
            </div>
            <Pagination links={pagination} />
        </main>
    );
}
