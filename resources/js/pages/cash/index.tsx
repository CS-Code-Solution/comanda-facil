import { Head, Link, useForm } from '@inertiajs/react';
import { ShiftSummary, type ShiftData } from '@/components/cash-summary';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import { Pagination } from '@/pages/dining/index';
type Links = { previous: string | null; next: string | null };
type Props = {
    shift: ShiftData | null;
    openUrl: string;
    operationId: string;
    accounts: { id: number; table: number; settled: boolean; url: string }[];
    pickupAccounts: {
        id: number;
        label: string;
        settled: boolean;
        url: string;
    }[];
    pickupLinks: Links;
    deliveryAccounts: {
        id: number;
        label: string;
        settled: boolean;
        url: string;
    }[];
    deliveryLinks: Links;
    pending: {
        reference: string;
        session: number;
        label: string;
        url: string;
    }[];
    shifts: { id: number; openedAt: string; closed: boolean; url: string }[];
    accountLinks: Links;
    pendingLinks: Links;
    shiftLinks: Links;
};
export default function CashIndex({
    shift,
    openUrl,
    operationId,
    accounts,
    pickupAccounts,
    pickupLinks,
    deliveryAccounts,
    deliveryLinks,
    pending,
    shifts,
    accountLinks,
    pendingLinks,
    shiftLinks,
}: Props) {
    const connection = useOperationsRealtime('operations.cashier');
    const form = useForm({ operation_id: operationId, opening: '' });
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Caixa" />
            <header className="flex flex-wrap justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold">Caixa</h1>
                    <p className="mt-2 text-sm" role="status">
                        {connection}
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href="/impressoes">Comprovantes de pedidos</Link>
                </Button>
            </header>
            {shift ? (
                <>
                    <ShiftSummary shift={shift} />
                    <Button asChild variant="outline">
                        <Link href={'/caixa/turnos/' + shift.id}>
                            Conferir ou fechar turno
                        </Link>
                    </Button>
                </>
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle>Abrir turno do restaurante</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="max-w-lg space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(openUrl);
                            }}
                        >
                            <Label htmlFor="opening">
                                Fundo inicial em dinheiro (R$)
                            </Label>
                            <Input
                                id="opening"
                                inputMode="decimal"
                                placeholder="0,00"
                                required
                                value={form.data.opening}
                                onChange={(event) =>
                                    form.setData('opening', event.target.value)
                                }
                            />
                            {Object.entries(form.errors).map(
                                ([key, message]) => (
                                    <InputError key={key} message={message} />
                                ),
                            )}
                            <Button disabled={form.processing}>
                                Abrir turno
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
            {pending.length > 0 && (
                <section className="space-y-3 rounded border border-amber-500 p-4">
                    <h2 className="text-xl font-semibold">
                        Devoluções pendentes
                    </h2>
                    <p>
                        O gestor deve conferir e registrar a devolução
                        efetivamente realizada.
                    </p>
                    {pending.map((row) => (
                        <p key={row.reference}>
                            <Link
                                className="break-all underline"
                                href={row.url}
                            >
                                {row.label} · {row.reference}
                            </Link>
                        </p>
                    ))}
                    <Pagination links={pendingLinks} />
                </section>
            )}
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Contas em atendimento</h2>
                {accounts.length === 0 && <p>Nenhuma conta aberta.</p>}
                <div className="grid gap-3 md:grid-cols-2">
                    {accounts.map((account) => (
                        <Card key={account.id}>
                            <CardContent className="flex items-center justify-between gap-3 pt-6">
                                <div>
                                    <p className="font-semibold">
                                        Mesa {account.table} · Conta #
                                        {account.id}
                                    </p>
                                    <p className="text-sm">
                                        {account.settled
                                            ? 'Pagamento registrado; conferir devoluções'
                                            : 'Pagamento ainda não registrado'}
                                    </p>
                                </div>
                                <Button asChild variant="outline">
                                    <Link href={account.url}>Abrir conta</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <Pagination links={accountLinks} />
            </section>
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Contas de retirada</h2>
                {pickupAccounts.length === 0 && <p>Nenhuma retirada aberta.</p>}
                {pickupAccounts.map((account) => (
                    <Card key={account.id}>
                        <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                            <p>
                                {account.label} ·{' '}
                                {account.settled
                                    ? 'Pagamento registrado'
                                    : 'Pagamento pendente'}
                            </p>
                            <Button asChild variant="outline">
                                <Link href={account.url}>Abrir retirada</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ))}
                <Pagination links={pickupLinks} />
            </section>
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Contas de entrega</h2>
                <p className="text-sm">
                    Pagamento registrado pelo entregador atribuído no
                    recebimento.
                </p>
                {!deliveryAccounts.length && <p>Nenhuma entrega aberta.</p>}
                {deliveryAccounts.map((account) => (
                    <p key={account.id}>
                        <Link className="underline" href={account.url}>
                            {account.label} ·{' '}
                            {account.settled
                                ? 'Pagamento registrado'
                                : 'Pagamento pendente'}
                        </Link>
                    </p>
                ))}
                <Pagination links={deliveryLinks} />
            </section>
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Histórico de turnos</h2>
                {shifts.map((row) => (
                    <p key={row.id}>
                        <Link className="underline" href={row.url}>
                            Turno #{row.id} ·{' '}
                            {row.closed ? 'Fechado' : 'Aberto'} ·{' '}
                            {new Date(row.openedAt).toLocaleString('pt-BR')}
                        </Link>
                    </p>
                ))}
                <Pagination links={shiftLinks} />
            </section>
        </div>
    );
}
