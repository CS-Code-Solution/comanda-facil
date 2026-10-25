import { Form, Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { printKind, printStatus } from './index';
type Line = {
    name: string;
    variant_name: string;
    quantity: number;
    note: string | null;
    total_label?: string;
    flavors?: { name: string }[];
    options: { name: string; quantity: number }[];
};
type Ticket = {
    reference: string;
    table: number;
    revision: number;
    status: string;
    items: Line[];
    previousItems: Line[];
    total_cents?: number;
    created_at: string;
};
type Job = {
    id: number;
    target: string;
    kind: string;
    copy: number;
    status: string;
    stale: boolean;
    claimUrl: string | null;
    canPrint: boolean;
    canReprint: boolean;
    actionUrl: string;
    operationId: string;
};
function Lines({ items }: { items: Line[] }) {
    return (
        <ol className="divide-y">
            {items.map((item, index) => (
                <li className="space-y-1 py-3" key={index}>
                    <p className="font-bold">
                        {item.quantity} × {item.name} · {item.variant_name}{' '}
                        {item.total_label && '· ' + item.total_label}
                    </p>
                    {item.flavors && item.flavors.length > 1 && (
                        <p>
                            Sabores:{' '}
                            {item.flavors
                                .map((flavor) => flavor.name)
                                .join(' / ')}
                        </p>
                    )}
                    {item.options.map((option, i) => (
                        <p key={i}>
                            {option.quantity} × {option.name}
                        </p>
                    ))}
                    {item.note && (
                        <p className="border p-2 font-bold">
                            Observação: {item.note}
                        </p>
                    )}
                </li>
            ))}
        </ol>
    );
}
export default function PrintDocument({
    ticket,
    job,
    events,
}: {
    ticket: Ticket;
    job: Job;
    events: {
        action: string;
        reason: string | null;
        actor: string | null;
        created_at: string;
    }[];
}) {
    const form = useForm({ reason: '', operation_id: job.operationId });
    const action = (value: string) =>
        form.post(job.actionUrl.replace('__action__', value), {
            preserveScroll: true,
        });
    return (
        <main className="mx-auto max-w-3xl space-y-5 p-4 text-foreground print:max-w-none print:p-0 print:text-black">
            <Head title={'Comprovante ' + job.id} />
            <style>
                {
                    '@media print { .print-controls { display:none !important; } body { background:white !important; } @page { margin:8mm; } }'
                }
            </style>
            <section className="print-controls space-y-4 rounded border p-4">
                <h1 className="text-2xl font-semibold">Impressão #{job.id}</h1>
                <p role="status">{printStatus[job.status]}</p>
                {job.stale && (
                    <p role="alert" className="font-semibold text-destructive">
                        Revisão antiga. A impressão está bloqueada; abra a fila
                        atualizada.
                    </p>
                )}
                {job.claimUrl && (
                    <Form action={job.claimUrl} method="post">
                        {({ processing, errors }) => (
                            <>
                                <Button disabled={processing}>
                                    Assumir impressão
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
                {job.canPrint && (
                    <>
                        <p>
                            A saída em papel precisa ser conferida. Fechar o
                            diálogo não registra sucesso.
                        </p>
                        <Button
                            type="button"
                            disabled={form.processing}
                            onClick={() =>
                                form.post(
                                    job.actionUrl.replace(
                                        '__action__',
                                        'claim',
                                    ),
                                    { onSuccess: () => window.print() },
                                )
                            }
                        >
                            Abrir diálogo de impressão
                        </Button>
                        <Button
                            className="ml-3"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => action('printed')}
                        >
                            Confirmo que saiu em papel
                        </Button>
                    </>
                )}
                {(job.canPrint || job.canReprint) && (
                    <div className="space-y-3">
                        <label htmlFor="print-reason">
                            Motivo obrigatório para falha ou nova cópia
                        </label>
                        <Input
                            id="print-reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            minLength={3}
                            maxLength={500}
                        />
                        {job.canPrint && (
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={form.processing}
                                onClick={() => action('failed')}
                            >
                                Registrar falha
                            </Button>
                        )}
                        {job.canReprint && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => action('reprint')}
                            >
                                Criar nova cópia para impressão
                            </Button>
                        )}
                    </div>
                )}
                {Object.entries(form.errors).map(([key, message]) => (
                    <InputError key={key} message={message} />
                ))}
                <p className="text-sm">
                    Impressão interrompida: após dois minutos, a fila permite
                    nova cópia com motivo. Confira se a anterior saiu para
                    evitar duplicidade.
                </p>
                <Link className="underline" href="/impressoes">
                    Voltar à fila
                </Link>
            </section>
            <article className="space-y-3 rounded border p-5 print:rounded-none print:border-0 print:p-0">
                <h2 className="text-xl font-bold">
                    Comanda Fácil ·{' '}
                    {job.target === 'kitchen' ? 'COZINHA' : 'CAIXA'}
                </h2>
                <p className="text-lg font-bold">
                    {printKind[job.kind]} · Mesa {ticket.table}
                </p>
                {job.stale && (
                    <p className="text-xl font-bold">
                        REVISÃO ANTIGA — NÃO EXECUTAR
                    </p>
                )}
                {job.copy > 1 && (
                    <p className="text-lg font-bold">
                        REIMPRESSÃO · Cópia {job.copy}
                    </p>
                )}
                <p className="text-sm break-all">Pedido: {ticket.reference}</p>
                <p>
                    Revisão {ticket.revision} · Comprovante #{job.id}
                </p>
                <p>{new Date(ticket.created_at).toLocaleString('pt-BR')}</p>
                {ticket.previousItems.length > 0 && (
                    <>
                        <h3 className="font-bold">
                            Itens anteriores — substituídos
                        </h3>
                        <Lines items={ticket.previousItems} />
                        <h3 className="font-bold">Itens atuais</h3>
                    </>
                )}
                {job.kind === 'cancellation' && (
                    <p className="border-y py-2 text-xl font-bold">
                        PEDIDO CANCELADO — NÃO PREPARAR / SERVIR
                    </p>
                )}
                <Lines items={ticket.items} />
                {ticket.total_cents !== undefined && (
                    <p className="border-t pt-3 font-bold">
                        {job.kind === 'cancellation'
                            ? 'Valor histórico cancelado'
                            : 'Total'}
                        :{' '}
                        {(ticket.total_cents / 100).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL',
                        })}
                    </p>
                )}
                <p className="border-t pt-3 text-sm font-bold">
                    COMPROVANTE DE PEDIDO — NÃO FISCAL
                </p>
                <p className="text-sm">Não comprova pagamento.</p>
            </article>
            <section className="print-controls space-y-2">
                <h2 className="font-semibold">Histórico desta impressão</h2>
                {events.map((event, index) => (
                    <p className="text-sm" key={index}>
                        {new Date(event.created_at).toLocaleString('pt-BR')} ·{' '}
                        {printStatus[event.action] ?? event.action} ·{' '}
                        {event.actor ?? 'Sistema'}{' '}
                        {event.reason && '· ' + event.reason}
                    </p>
                ))}
            </section>
        </main>
    );
}
