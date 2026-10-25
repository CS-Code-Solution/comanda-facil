import { Head, Link } from '@inertiajs/react';
import { ShiftSummary, type ShiftData } from '@/components/cash-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import { Pagination } from '@/pages/dining/index';

type Links = { previous: string | null; next: string | null };
export default function ShiftReport({
    shift,
    frozen,
    legacy,
    generatedAt,
    modalities,
    waiters,
    waiterLinks,
    qrOrders,
    unknownAuthorship,
    unassignedCount,
    unassignedUrl,
    shifts,
    shiftLinks,
}: {
    shift: ShiftData | null;
    frozen: boolean;
    legacy: boolean;
    generatedAt: string;
    modalities: {
        modality: string;
        label: string;
        orders: number;
        active: number;
        cancelled: number;
        cancelledValue: string;
        payments: number;
        gross: string;
        discount: string;
        fee: string;
        received: string;
        refunds: number;
        refunded: string;
        net: string;
    }[];
    waiters: {
        actor_id: number;
        name: string;
        tables: number;
        orders: number;
        cancelled: number;
    }[];
    waiterLinks: Links;
    qrOrders: number;
    unknownAuthorship: number;
    unassignedCount: number;
    unassignedUrl: string;
    shifts: { id: number; openedAt: string; closed: boolean; url: string }[];
    shiftLinks: Links;
}) {
    const connection = useOperationsRealtime('operations.reports');
    return (
        <main className="space-y-6 p-4 md:p-6">
            <Head title="Relatórios gerenciais" />
            <header className="space-y-2">
                <h1 className="text-3xl font-semibold">
                    Relatório gerencial{' '}
                    {shift ? '· Turno #' + shift.id : '· Pedidos sem turno'}
                </h1>
                <p role="status" className="text-sm">
                    {connection}
                </p>
                <p>
                    {frozen
                        ? 'Resumo preservado no fechamento do turno.'
                        : 'Posição atual; os números são atualizados durante a operação.'}{' '}
                    Gerado em {new Date(generatedAt).toLocaleString('pt-BR')}.
                </p>
                {legacy && (
                    <p
                        role="status"
                        className="rounded border border-amber-500 p-3"
                    >
                        Turno anterior aos snapshots de relatório: os movimentos
                        financeiros são os registros originais do turno; as
                        contagens e cancelamentos de pedidos refletem o estado
                        atual.
                    </p>
                )}
            </header>
            <Card>
                <CardHeader>
                    <CardTitle>
                        Pedidos lançados e valores recebidos por modalidade
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="mb-4 text-sm">
                        Pedidos pertencem ao turno em que foram lançados.
                        Pagamentos e devoluções pertencem ao turno em que foram
                        registrados, mesmo quando o pedido foi lançado antes.
                        Cancelamento preserva o valor histórico; devolução
                        aparece no movimento financeiro.
                    </p>
                    <div className="grid gap-4 lg:grid-cols-3">
                        {modalities.map((mode) => (
                            <section
                                key={mode.modality}
                                className="space-y-2 rounded border p-4"
                            >
                                <h2 className="text-xl font-semibold">
                                    {mode.label}
                                </h2>
                                <p>
                                    Pedidos lançados: {mode.orders} · Ativos:{' '}
                                    {mode.active}
                                </p>
                                <p>
                                    Cancelados: {mode.cancelled} · Valor
                                    histórico: {mode.cancelledValue}
                                </p>
                                <hr className="my-3" />
                                <p>Contas recebidas: {mode.payments}</p>
                                <p>Itens brutos recebidos: {mode.gross}</p>
                                <p>Descontos: {mode.discount}</p>
                                <p>Taxas de entrega recebidas: {mode.fee}</p>
                                <p className="font-semibold">
                                    Recebimentos: {mode.received}
                                </p>
                                <p>
                                    Devoluções: {mode.refunds} · {mode.refunded}
                                </p>
                                <p className="font-semibold">
                                    Líquido recebido: {mode.net}
                                </p>
                            </section>
                        ))}
                    </div>
                    {!shift && (
                        <p className="mt-4 text-sm">
                            Pedidos sem turno não são atribuídos automaticamente
                            ao turno seguinte. Seus pagamentos aparecerão no
                            turno real do recebimento.
                        </p>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Mesas distintas por garçom</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-sm">
                        Cada garçom conta uma mesa se lançou pelo menos um
                        pedido nela neste turno, inclusive pedido cancelado.
                        Repetir pedidos ou reabrir a mesma mesa não aumenta essa
                        contagem. Dois garçons na mesma mesa contam uma para
                        cada.
                    </p>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs sm:text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-2 sm:p-3">Garçom</th>
                                    <th className="p-2 sm:p-3">
                                        Mesas distintas
                                    </th>
                                    <th className="p-2 sm:p-3">
                                        Pedidos lançados
                                    </th>
                                    <th className="p-2 sm:p-3">Cancelados</th>
                                </tr>
                            </thead>
                            <tbody>
                                {waiters.map((waiter) => (
                                    <tr
                                        className="border-b"
                                        key={waiter.actor_id}
                                    >
                                        <td className="p-2 sm:p-3">
                                            {waiter.name}
                                        </td>
                                        <td className="p-2 sm:p-3">
                                            {waiter.tables}
                                        </td>
                                        <td className="p-2 sm:p-3">
                                            {waiter.orders}
                                        </td>
                                        <td className="p-2 sm:p-3">
                                            {waiter.cancelled}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {!waiters.length && (
                        <p>Nenhum pedido lançado por garçom neste grupo.</p>
                    )}
                    <Pagination links={waiterLinks} />
                    <p>Pedidos pelo QR sem atribuição a garçom: {qrOrders}.</p>
                    {unknownAuthorship > 0 && (
                        <p role="status">
                            Pedidos antigos da equipe sem papel histórico:{' '}
                            {unknownAuthorship}. Não foram atribuídos a garçom
                            com base no papel atual.
                        </p>
                    )}
                </CardContent>
            </Card>
            {shift && (
                <>
                    <ShiftSummary shift={shift} />
                    <p className="text-sm">
                        O fundo inicial compõe o dinheiro esperado, sem compor
                        vendas. Troco não é receita. Diferenças são o valor
                        conferido menos o esperado. Em turno aberto ainda não há
                        conferência final.
                    </p>
                    <Button asChild variant="outline">
                        <Link href={'/caixa/turnos/' + shift.id}>
                            Abrir conferência do turno
                        </Link>
                    </Button>
                </>
            )}
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Consultar outro turno</h2>
                <Button asChild variant="outline">
                    <Link href={unassignedUrl}>
                        Pedidos sem turno ({unassignedCount})
                    </Link>
                </Button>
                <div className="grid gap-2 md:grid-cols-2">
                    {shifts.map((item) => (
                        <Link
                            className="rounded border p-3 underline"
                            href={item.url}
                            key={item.id}
                        >
                            Turno #{item.id} ·{' '}
                            {item.closed ? 'Fechado' : 'Aberto'} ·{' '}
                            {new Date(item.openedAt).toLocaleString('pt-BR')}
                        </Link>
                    ))}
                </div>
                <Pagination links={shiftLinks} />
            </section>
        </main>
    );
}
