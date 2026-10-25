import { Form, Head, Link, useForm, usePoll } from '@inertiajs/react';
import DiningOrderItems, {
    type OrderLine,
} from '@/components/dining-order-items';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from './index';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
type Props = {
    table: {
        id: number;
        number: number;
        label: string | null;
        is_active: boolean;
        is_blocked: boolean;
    };
    session: {
        id: number;
        total: string;
        settled: boolean;
        refundDue: string;
        hasRefundDue: boolean;
        cashUrl: string | null;
        closeUrl: string | null;
    } | null;
    orders: {
        reference: string;
        status: string;
        url: string;
        tableNumber: number;
        total: string;
        createdAt: string;
        items: OrderLine[];
        source: string;
        actor: string | null;
    }[];
    pagination: { previous: string | null; next: string | null };
    canManage: boolean;
    canOrder: boolean;
    orderUrl: string;
    qrUrl: string;
    updateUrl: string;
    rotateUrl: string;
};
export default function DiningTable({
    table,
    session,
    orders,
    pagination,
    canManage,
    canOrder,
    orderUrl,
    qrUrl,
    updateUrl,
    rotateUrl,
}: Props) {
    usePoll(5000, { only: ['table', 'session', 'orders', 'pagination'] });
    const connection = useOperationsRealtime('operations.dining');
    const form = useForm({
        label: table.label ?? '',
        is_active: table.is_active,
        is_blocked: table.is_blocked,
        reason: '',
    });
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={'Mesa ' + table.number} />
            <p className="text-sm" role="status">
                {connection}
            </p>
            <div className="flex flex-wrap justify-between gap-4">
                <Heading
                    title={'Mesa ' + table.number}
                    description={
                        table.label ??
                        'Sem responsável fixo. Cada pedido registra seu autor.'
                    }
                />
                <div className="flex gap-3">
                    {canOrder && (
                        <Button asChild>
                            <Link href={orderUrl}>Lançar pedido</Link>
                        </Button>
                    )}
                    {canManage && (
                        <Button asChild variant="outline">
                            <Link href={qrUrl}>QR da mesa</Link>
                        </Button>
                    )}
                    <Button asChild variant="outline">
                        <Link href="/salao">Voltar</Link>
                    </Button>
                </div>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Atendimento atual</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {session ? (
                        <>
                            <p>
                                Atendimento #{session.id} · Total de pedidos:{' '}
                                <strong>{session.total}</strong>
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {session.settled
                                    ? 'Conta quitada. O atendimento pode ser encerrado.'
                                    : 'O encerramento exige conta quitada ou total zero.'}
                            </p>
                            {session.hasRefundDue && (
                                <p role="alert">
                                    Devolução pendente: {session.refundDue}.
                                    Procure o gestor.
                                </p>
                            )}
                            {session.cashUrl && (
                                <Button asChild variant="outline">
                                    <Link href={session.cashUrl}>
                                        Receber pagamento / ver conta
                                    </Link>
                                </Button>
                            )}
                            {session.closeUrl && (
                                <Form
                                    action={session.closeUrl}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <Button
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Encerrar atendimento
                                            </Button>
                                            <InputError
                                                message={errors.close}
                                            />
                                        </>
                                    )}
                                </Form>
                            )}
                        </>
                    ) : (
                        <p>
                            Nenhum atendimento aberto. O primeiro pedido
                            confirmado abre o atendimento automaticamente.
                        </p>
                    )}
                </CardContent>
            </Card>
            <section className="space-y-4">
                <h2 className="text-xl font-semibold">Pedidos da mesa</h2>
                {orders.map((order) => (
                    <Card key={order.reference}>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {order.source}
                                {order.actor && ' · ' + order.actor}
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                {order.status} ·{' '}
                                {new Date(order.createdAt).toLocaleString(
                                    'pt-BR',
                                )}
                            </p>
                            <p className="text-xs break-all text-muted-foreground">
                                {order.reference}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <DiningOrderItems items={order.items} />
                            <p className="border-t pt-3 font-semibold">
                                Total: {order.total}
                            </p>
                            <Button asChild variant="outline" className="mt-3">
                                <Link href={order.url}>
                                    Acompanhar e operar pedido
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ))}
                {orders.length === 0 && (
                    <p className="text-muted-foreground">
                        Nenhum pedido registrado.
                    </p>
                )}
                <Pagination links={pagination} />
            </section>
            {canManage && (
                <Card>
                    <CardHeader>
                        <CardTitle>Configuração da mesa</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.put(updateUrl, {
                                    preserveScroll: true,
                                    onSuccess: () => form.reset('reason'),
                                });
                            }}
                        >
                            <Label htmlFor="label">Identificação</Label>
                            <Input
                                id="label"
                                value={form.data.label}
                                onChange={(event) =>
                                    form.setData('label', event.target.value)
                                }
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_active',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Mesa ativa
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_blocked}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_blocked',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Bloquear pedidos desta mesa
                            </label>
                            <Label htmlFor="table-reason">
                                Motivo obrigatório
                            </Label>
                            <Input
                                id="table-reason"
                                minLength={3}
                                required
                                maxLength={500}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                            {Object.entries(form.errors).map(
                                ([key, message]) => (
                                    <InputError key={key} message={message} />
                                ),
                            )}
                            <div className="flex flex-wrap gap-3">
                                <Button disabled={form.processing}>
                                    Salvar configuração
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={
                                        form.processing ||
                                        form.data.reason.length < 3
                                    }
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                'Gerar outro QR invalida o QR impresso. Confirma a troca?',
                                            )
                                        )
                                            form.post(rotateUrl, {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    form.reset('reason'),
                                            });
                                    }}
                                >
                                    Trocar QR e revogar o anterior
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Troque o QR em caso de divulgação indevida e
                                substitua o impresso. Nenhum pedido existente é
                                apagado.
                            </p>
                        </form>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
DiningTable.layout = {
    breadcrumbs: [
        { title: 'Salão', href: '/salao' },
        { title: 'Mesa', href: '#' },
    ],
};
