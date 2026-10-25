import { Head, Link, useForm } from '@inertiajs/react';
import { ShiftSummary, type ShiftData } from '@/components/cash-summary';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import { Pagination } from '@/pages/dining/index';
type Props = {
    shift: ShiftData;
    operationId: string;
    closeUrl: string;
    refreshUrl: string;
    payments: {
        reference: string;
        method: string;
        amount: string;
        url: string;
    }[];
    refunds: {
        reference: string;
        paymentId: number;
        method: string;
        amount: string;
        reason: string;
    }[];
    paymentLinks: { previous: string | null; next: string | null };
    refundLinks: { previous: string | null; next: string | null };
};
export default function Shift({
    shift,
    operationId,
    closeUrl,
    refreshUrl,
    payments,
    refunds,
    paymentLinks,
    refundLinks,
}: Props) {
    const connection = useOperationsRealtime('operations.cashier');
    const form = useForm({
        operation_id: operationId,
        version: shift.version,
        counted: { cash: '', pix: '', card: '' },
        reason: '',
    });
    return (
        <div className="space-y-5 p-4 md:p-6">
            <Head title={'Turno ' + shift.id} />
            <header className="flex flex-wrap justify-between gap-3">
                <h1 className="text-2xl font-semibold">
                    Conferência do turno #{shift.id}
                </h1>
                <Link className="underline" href="/caixa">
                    Voltar ao caixa
                </Link>
            </header>
            <p className="text-sm" role="status">
                {connection}
            </p>
            <ShiftSummary shift={shift} />
            {!shift.closedAt && (
                <Card>
                    <CardHeader>
                        <CardTitle>Fechar turno</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(closeUrl, { preserveScroll: true });
                            }}
                        >
                            <p>
                                Conte o dinheiro disponível e confira Pix/cartão
                                pelos recebimentos menos devoluções, sem
                                descontar tarifas.
                            </p>
                            {form.data.version !== shift.version && (
                                <p role="alert" className="rounded border p-3">
                                    Houve novos lançamentos. Atualize a
                                    conferência e confira os valores novamente.
                                </p>
                            )}
                            <div className="grid gap-4 md:grid-cols-3">
                                {(['cash', 'pix', 'card'] as const).map(
                                    (method) => (
                                        <div key={method} className="space-y-2">
                                            <Label
                                                htmlFor={'counted-' + method}
                                            >
                                                {method === 'cash'
                                                    ? 'Dinheiro contado'
                                                    : method === 'pix'
                                                      ? 'Pix conferido'
                                                      : 'Cartão conferido'}{' '}
                                                (R$)
                                            </Label>
                                            <Input
                                                id={'counted-' + method}
                                                required
                                                placeholder="0,00"
                                                value={
                                                    form.data.counted[method]
                                                }
                                                onChange={(event) =>
                                                    form.setData('counted', {
                                                        ...form.data.counted,
                                                        [method]:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    ),
                                )}
                            </div>
                            <Label htmlFor="closing-reason">
                                Motivo das diferenças (obrigatório se houver
                                diferença)
                            </Label>
                            <Input
                                id="closing-reason"
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
                                    Confirmar fechamento
                                </Button>
                                <Button asChild variant="outline">
                                    <a href={refreshUrl}>
                                        Atualizar conferência
                                    </a>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">
                    Recebimentos deste turno
                </h2>
                {payments.length === 0 && <p>Nenhum recebimento.</p>}
                {payments.map((row) => (
                    <p key={row.reference}>
                        <Link className="break-all underline" href={row.url}>
                            {row.method} · {row.amount} · {row.reference}
                        </Link>
                    </p>
                ))}
                <Pagination links={paymentLinks} />
            </section>
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">
                    Devoluções deste turno
                </h2>
                {refunds.length === 0 && <p>Nenhuma devolução.</p>}
                {refunds.map((row) => (
                    <p className="break-all" key={row.reference}>
                        {row.method} · {row.amount} · Pagamento #{row.paymentId}{' '}
                        · {row.reason}
                    </p>
                ))}
                <Pagination links={refundLinks} />
            </section>
        </div>
    );
}
