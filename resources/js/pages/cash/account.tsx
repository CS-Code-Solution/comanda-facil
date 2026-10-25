import { Form, Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { CashQuote, type CashQuoteData } from '@/components/cash-summary';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
export type PaymentData = {
    id: number;
    reference: string;
    amount: string;
    method: string;
    received: string | null;
    change: string;
    externalReference: string | null;
    createdAt: string;
    shiftId: number;
    quote: CashQuoteData;
};
type Props = {
    account: {
        id: number;
        table: number | null;
        locationLabel: string;
        closed: boolean;
        settled: boolean;
        legacy: boolean;
        quote: CashQuoteData;
        refundDue: string;
        refunded: string;
        hasRefundDue: boolean;
        closeUrl: string | null;
    };
    orders: { reference: string; url: string }[];
    backUrl: string;
    backLabel: string;
    realtimeChannel: string;
    canPay: boolean;
    hasShift: boolean;
    canDiscount: boolean;
    previewUrl: string;
    payment: PaymentData | null;
    canRefund: boolean;
    refundDefault: string;
    refundOperation: string;
    refundUrl: string | null;
    refunds: {
        reference: string;
        shift: number;
        method: string;
        amount: string;
        reason: string;
        createdAt: string;
    }[];
};
export default function Account({
    account,
    orders,
    backUrl,
    backLabel,
    realtimeChannel,
    canPay,
    hasShift,
    canDiscount,
    previewUrl,
    payment,
    canRefund,
    refundDefault,
    refundOperation,
    refundUrl,
    refunds,
}: Props) {
    const connection = useOperationsRealtime(realtimeChannel);
    const [manual, setManual] = useState(false);
    const preview = useForm({ type: 'percentage', value: '', reason: '' });
    const refund = useForm({
        operation_id: refundOperation,
        method: 'cash',
        amount: refundDefault,
        reason: '',
        external_reference: '',
        performed: false,
    });
    return (
        <div className="space-y-5 p-4 md:p-6">
            <Head title={'Conta ' + account.id} />
            <header className="flex flex-wrap justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-semibold">
                        {account.locationLabel}
                    </h1>
                    <p className="mt-2 text-sm" role="status">
                        {connection}
                    </p>
                </div>
                <Link className="underline" href={backUrl}>
                    {backLabel}
                </Link>
            </header>
            <p>
                {account.closed
                    ? 'Atendimento encerrado'
                    : 'Atendimento aberto'}{' '}
                ·{' '}
                {account.settled
                    ? 'Quitação registrada'
                    : 'Aguardando pagamento'}
            </p>
            {account.legacy && (
                <p role="alert" className="rounded border p-3">
                    Quitação legada sem registro financeiro. Confira o histórico
                    antes de alterar valores.
                </p>
            )}
            {!hasShift && (
                <p role="status" className="rounded border p-3">
                    É necessário um turno aberto pelo caixa ou gestor para
                    registrar pagamento ou devolução.
                </p>
            )}
            <CashQuote quote={account.quote} />
            {canPay && (
                <Card>
                    <CardHeader>
                        <CardTitle>Preparar pagamento integral</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="max-w-xl space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                preview.transform((data) => ({
                                    discount: manual ? data : null,
                                }));
                                preview.post(previewUrl);
                            }}
                        >
                            <p>
                                Confira o total e escolha a forma de pagamento
                                na próxima tela. Uma conta corresponde a um
                                pagamento.
                            </p>
                            {canDiscount && (
                                <>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={manual}
                                            onChange={(event) =>
                                                setManual(event.target.checked)
                                            }
                                        />
                                        Conceder desconto de gestor no
                                        fechamento
                                    </label>
                                    {manual && (
                                        <>
                                            <Label htmlFor="discount-type">
                                                Tipo de desconto
                                            </Label>
                                            <select
                                                id="discount-type"
                                                className="h-10 w-full rounded border bg-background px-3"
                                                value={preview.data.type}
                                                onChange={(event) =>
                                                    preview.setData(
                                                        'type',
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                <option value="percentage">
                                                    Percentual (%)
                                                </option>
                                                <option value="fixed">
                                                    Valor fixo da conta (R$)
                                                </option>
                                            </select>
                                            <Label htmlFor="discount-value">
                                                Valor do desconto
                                            </Label>
                                            <Input
                                                id="discount-value"
                                                required
                                                value={preview.data.value}
                                                onChange={(event) =>
                                                    preview.setData(
                                                        'value',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Label htmlFor="discount-reason">
                                                Motivo do desconto
                                            </Label>
                                            <Input
                                                id="discount-reason"
                                                required
                                                minLength={3}
                                                maxLength={500}
                                                value={preview.data.reason}
                                                onChange={(event) =>
                                                    preview.setData(
                                                        'reason',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <p className="text-sm">
                                                Será aplicado o melhor desconto
                                                por item, sem acumular com
                                                promoções.
                                            </p>
                                        </>
                                    )}
                                </>
                            )}
                            {Object.entries(preview.errors).map(
                                ([key, message]) => (
                                    <InputError key={key} message={message} />
                                ),
                            )}
                            <Button disabled={preview.processing}>
                                Conferir pagamento
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
            {payment && (
                <Card>
                    <CardHeader>
                        <CardTitle>Recebimento original</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <p className="text-sm break-all">{payment.reference}</p>
                        <p className="font-semibold">
                            {payment.method} · {payment.amount} · Turno #
                            {payment.shiftId}
                        </p>
                        {payment.received && (
                            <>
                                <p>Recebido: {payment.received}</p>
                                <p className="font-semibold">
                                    Troco: {payment.change}
                                </p>
                            </>
                        )}
                        <p>
                            {new Date(payment.createdAt).toLocaleString(
                                'pt-BR',
                            )}
                        </p>
                        <p>Devoluções registradas: {account.refunded}</p>
                        <p className="font-semibold">
                            Saldo a devolver: {account.refundDue}
                        </p>
                        <Button asChild variant="outline">
                            <Link
                                href={
                                    '/caixa/pagamentos/' +
                                    payment.id +
                                    '/comprovante'
                                }
                            >
                                Ver registro de pagamento
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            )}
            {account.hasRefundDue && (
                <p
                    role="alert"
                    className="rounded border border-amber-500 p-4 font-semibold"
                >
                    Há devolução pendente de {account.refundDue}. O gestor deve
                    registrar a devolução realizada.
                </p>
            )}
            {canRefund && refundUrl && (
                <Card>
                    <CardHeader>
                        <CardTitle>Registrar devolução realizada</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="max-w-xl space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                refund.post(refundUrl, {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        refund.reset(
                                            'reason',
                                            'performed',
                                            'external_reference',
                                        ),
                                });
                            }}
                        >
                            <Label htmlFor="refund-method">
                                Forma efetiva da devolução
                            </Label>
                            <select
                                id="refund-method"
                                className="h-10 w-full rounded border bg-background px-3"
                                value={refund.data.method}
                                onChange={(event) =>
                                    refund.setData('method', event.target.value)
                                }
                            >
                                <option value="cash">Dinheiro</option>
                                <option value="pix">Pix</option>
                                <option value="card">Cartão</option>
                            </select>
                            <Label htmlFor="refund-amount">
                                Valor devolvido (R$)
                            </Label>
                            <Input
                                id="refund-amount"
                                required
                                value={refund.data.amount}
                                onChange={(event) =>
                                    refund.setData('amount', event.target.value)
                                }
                            />
                            <Label htmlFor="refund-reason">
                                Motivo da devolução
                            </Label>
                            <Input
                                id="refund-reason"
                                required
                                minLength={3}
                                maxLength={500}
                                value={refund.data.reason}
                                onChange={(event) =>
                                    refund.setData('reason', event.target.value)
                                }
                            />
                            <Label htmlFor="refund-reference">
                                Referência interna opcional (sem dados bancários
                                ou de cartão)
                            </Label>
                            <Input
                                id="refund-reference"
                                maxLength={100}
                                value={refund.data.external_reference}
                                onChange={(event) =>
                                    refund.setData(
                                        'external_reference',
                                        event.target.value,
                                    )
                                }
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    required
                                    checked={refund.data.performed}
                                    onChange={(event) =>
                                        refund.setData(
                                            'performed',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Confirmo que a devolução já foi realizada fora
                                do sistema
                            </label>
                            {Object.entries(refund.errors).map(
                                ([key, message]) => (
                                    <InputError key={key} message={message} />
                                ),
                            )}
                            <Button disabled={refund.processing}>
                                Registrar devolução
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
            {refunds.length > 0 && (
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Histórico de devoluções
                    </h2>
                    {refunds.map((row) => (
                        <p className="rounded border p-3" key={row.reference}>
                            {row.method} · {row.amount} · Turno #{row.shift} ·{' '}
                            {row.reason} ·{' '}
                            {new Date(row.createdAt).toLocaleString('pt-BR')}
                        </p>
                    ))}
                </section>
            )}
            {account.closeUrl && (
                <Form action={account.closeUrl} method="post">
                    {({ processing, errors }) => (
                        <>
                            <Button variant="outline" disabled={processing}>
                                Encerrar atendimento da mesa
                            </Button>
                            {Object.entries(errors).map(([key, message]) => (
                                <InputError key={key} message={message} />
                            ))}
                        </>
                    )}
                </Form>
            )}
            <section className="space-y-2">
                <h2 className="text-xl font-semibold">Pedidos desta conta</h2>
                {orders.map((order) => (
                    <p key={order.reference}>
                        <Link className="break-all underline" href={order.url}>
                            {order.reference}
                        </Link>
                    </p>
                ))}
            </section>
        </div>
    );
}
