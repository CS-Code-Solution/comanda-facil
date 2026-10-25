import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
export type OperationOrder = {
    id: number;
    reference: string;
    tableNumber: number;
    version: number;
    revision: number;
    status: string;
    createdAt: string;
    total: string | null;
    url: string;
    commandUrl: string;
    editUrl: string | null;
    next: { status: string; label: string; operation_id: string } | null;
    cancelOperation: string | null;
    items: {
        name: string;
        presentation: string;
        quantity: number;
        note: string | null;
        options: string[];
        total?: string;
    }[];
};
export default function OperationsOrder({ order }: { order: OperationOrder }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    Mesa {order.tableNumber} · {order.status}
                </CardTitle>
                <p className="text-sm">
                    {new Date(order.createdAt).toLocaleString('pt-BR')} ·
                    Revisão {order.revision}
                </p>
                <Link className="text-xs break-all underline" href={order.url}>
                    {order.reference}
                </Link>
            </CardHeader>
            <CardContent className="space-y-4">
                <ul className="divide-y">
                    {order.items.map((item, i) => (
                        <li key={i} className="space-y-1 py-3">
                            <p className="font-semibold">
                                {item.quantity} × {item.name} ·{' '}
                                {item.presentation}{' '}
                                {item.total && '· ' + item.total}
                            </p>
                            {item.options.map((option, index) => (
                                <p key={index}>{option}</p>
                            ))}
                            {item.note && (
                                <p className="rounded border p-2">
                                    Observação: {item.note}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
                {order.total && (
                    <p className="font-semibold">Valor devido: {order.total}</p>
                )}
                {order.next && (
                    <Form
                        action={order.commandUrl}
                        method="post"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="version"
                                    value={order.version}
                                />
                                <input
                                    type="hidden"
                                    name="operation_id"
                                    value={order.next!.operation_id}
                                />
                                <input
                                    type="hidden"
                                    name="status"
                                    value={order.next!.status}
                                />
                                <Button disabled={processing}>
                                    Marcar {order.next!.label}
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
                {order.editUrl && (
                    <Button asChild variant="outline">
                        <Link href={order.editUrl}>Corrigir itens</Link>
                    </Button>
                )}
                {order.cancelOperation && (
                    <details className="rounded border p-3">
                        <summary className="cursor-pointer">
                            Cancelar pedido
                        </summary>
                        <Form
                            className="mt-3 space-y-3"
                            action={order.commandUrl}
                            method="post"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="version"
                                        value={order.version}
                                    />
                                    <input
                                        type="hidden"
                                        name="operation_id"
                                        value={order.cancelOperation!}
                                    />
                                    <input
                                        type="hidden"
                                        name="status"
                                        value="cancelled"
                                    />
                                    <label
                                        className="text-sm"
                                        htmlFor={'cancel-' + order.id}
                                    >
                                        Motivo obrigatório do cancelamento
                                    </label>
                                    <Input
                                        id={'cancel-' + order.id}
                                        name="reason"
                                        minLength={3}
                                        maxLength={500}
                                        required
                                    />
                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Confirmar cancelamento
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
                    </details>
                )}
            </CardContent>
        </Card>
    );
}
