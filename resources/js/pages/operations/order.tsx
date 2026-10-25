import { Form, Head, Link } from '@inertiajs/react';
import OperationsOrder, {
    type OperationOrder,
} from '@/components/operations-order';
import DiningOrderItems, {
    type OrderLine,
} from '@/components/dining-order-items';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import { Pagination } from '@/pages/dining/index';
type HistorySnapshot = { status?: string; total?: string; items?: OrderLine[] };
function Snapshot({ value }: { value: HistorySnapshot }) {
    return value.items ? (
        <div>
            <p>
                {value.status} · {value.total}
            </p>
            <DiningOrderItems items={value.items} />
        </div>
    ) : (
        <p>Pedido ainda não registrado.</p>
    );
}
type History = {
    version: number;
    action: string;
    actor: string;
    reason: string | null;
    createdAt: string;
    before: HistorySnapshot;
    after: HistorySnapshot;
};
export default function Order({
    order,
    history,
    printsUrl,
    legacyPrintUrl,
    pagination,
}: {
    order: OperationOrder;
    history: History[];
    printsUrl: string | null;
    legacyPrintUrl: string | null;
    pagination: { previous: string | null; next: string | null };
}) {
    // Staff order pages use only the staff scope authorized for their workspace.
    const connection = useOperationsRealtime(
        order.total === null ? 'operations.kitchen' : 'operations.dining',
    );
    return (
        <main className="mx-auto max-w-4xl space-y-5 p-4 md:p-6">
            <Head title="Acompanhar pedido" />
            <h1 className="text-2xl font-semibold">Acompanhar pedido</h1>
            <p className="text-sm" role="status">
                {connection}
            </p>
            <OperationsOrder order={order} />
            <div className="flex flex-wrap gap-3">
                {printsUrl && (
                    <Button asChild variant="outline">
                        <Link href={printsUrl}>Ver comprovantes</Link>
                    </Button>
                )}
                {legacyPrintUrl && (
                    <Form action={legacyPrintUrl} method="post">
                        {({ processing, errors }) => (
                            <>
                                <Button variant="outline" disabled={processing}>
                                    Gerar comprovantes ausentes
                                </Button>
                                {Object.entries(errors).map(
                                    ([key, message]) => (
                                        <InputError
                                            key={key}
                                            message={message}
                                        />
                                    ),
                                )}
                            </>
                        )}
                    </Form>
                )}
            </div>
            {history.length > 0 && (
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Histórico de alterações
                    </h2>
                    {history.map((row) => (
                        <details
                            className="rounded border p-3"
                            key={row.version}
                        >
                            <summary>
                                Versão {row.version} · {row.action} ·{' '}
                                {row.actor}
                            </summary>
                            <p>
                                {new Date(row.createdAt).toLocaleString(
                                    'pt-BR',
                                )}
                            </p>
                            {row.reason && <p>Motivo: {row.reason}</p>}
                            <div className="mt-3 grid gap-3 md:grid-cols-2">
                                <div>
                                    <h3>Antes</h3>
                                    <Snapshot value={row.before} />
                                </div>
                                <div>
                                    <h3>Depois</h3>
                                    <Snapshot value={row.after} />
                                </div>
                            </div>
                        </details>
                    ))}
                    <Pagination links={pagination} />
                </section>
            )}
        </main>
    );
}
