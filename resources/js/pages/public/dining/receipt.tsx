import { Head, Link } from '@inertiajs/react';
import DiningOrderItems, {
    type OrderLine,
} from '@/components/dining-order-items';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
export default function DiningReceipt({
    order,
    backUrl,
    realtimeAuthUrl,
}: {
    order: {
        reference: string;
        tableNumber: number | null;
        locationLabel: string;
        modality: string;
        status: string;
        total: string;
        createdAt: string;
        items: OrderLine[];
        timeline: { label: string; at: string }[];
        notice: string | null;
        deliveryFee?: string | null;
    };
    backUrl: string;
    realtimeAuthUrl: string;
}) {
    const connection = useOperationsRealtime(
        (order.modality === 'delivery'
            ? 'delivery.order.'
            : order.modality === 'pickup'
              ? 'pickup.order.'
              : 'dining.order.') + order.reference,
        realtimeAuthUrl,
    );
    return (
        <main className="mx-auto max-w-2xl space-y-6 p-4 md:p-6">
            <Head title="Acompanhar pedido" />
            <div>
                <h1 className="text-3xl font-semibold">Acompanhar pedido</h1>
                <p className="mt-2 text-sm" role="status">
                    {connection}
                </p>
                <p className="mt-2">
                    {order.locationLabel} ·{' '}
                    {new Date(order.createdAt).toLocaleString('pt-BR')}
                </p>
                <p className="mt-2 text-xs break-all text-muted-foreground">
                    Referência: {order.reference}
                </p>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle aria-live="polite">{order.status}</CardTitle>
                </CardHeader>
                <CardContent>
                    {order.notice && (
                        <p className="mb-3 rounded border p-3" role="status">
                            {order.notice}
                        </p>
                    )}
                    <DiningOrderItems items={order.items} />
                    <ol className="space-y-2 border-t py-4">
                        {order.timeline.map((step) => (
                            <li key={step.label}>
                                {step.label} ·{' '}
                                {new Date(step.at).toLocaleString('pt-BR')}
                            </li>
                        ))}
                    </ol>
                    {order.deliveryFee && (
                        <p className="border-t pt-4">
                            Taxa de entrega incluída: {order.deliveryFee}
                        </p>
                    )}
                    <p className="border-t pt-4 text-xl font-semibold">
                        Total: {order.total}
                    </p>
                    <p className="mt-3 text-sm text-muted-foreground">
                        Pagamento presencial. Este registro não é documento
                        fiscal.
                    </p>
                    {order.modality === 'pickup' && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Aguarde ficar pronto e pague no caixa antes de
                            retirar. Acompanhe neste navegador; o acesso vale
                            por duas horas. Se perder o acesso, procure a equipe
                            com a referência.
                        </p>
                    )}
                    {order.modality === 'delivery' && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Pague ao entregador do restaurante no recebimento.
                            Acompanhe neste navegador; o acesso vale por duas
                            horas. Se perder o acesso, procure a equipe com a
                            referência.
                        </p>
                    )}
                </CardContent>
            </Card>
            <Button asChild variant="outline">
                <Link href={backUrl}>Voltar ao cardápio</Link>
            </Button>
        </main>
    );
}
