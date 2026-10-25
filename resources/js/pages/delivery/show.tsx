import { Head, Link, useForm } from '@inertiajs/react';
import {
    AddressSummary,
    type DeliveryAddress,
} from '@/components/delivery-address';
import DiningOrderItems, {
    type OrderLine,
} from '@/components/dining-order-items';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';

export default function DeliveryShow(props: {
    order: {
        reference: string;
        locationLabel: string;
        status: string;
        total: string;
        deliveryFee: string;
        items: OrderLine[];
    };
    version: number;
    contact: { phone: string; address: DeliveryAddress } | null;
    courier: string | null;
    couriers: { id: number; name: string }[];
    canAssign: boolean;
    assignUrl: string;
    assignOperation: string;
    next: { status: string; label: string } | null;
    commandUrl: string;
    operationId: string;
    cashUrl: string | null;
    operationsUrl: string | null;
}) {
    const { order } = props;
    const connection = useOperationsRealtime('operations.delivery');
    const assignment = useForm({
        courier_id: '',
        version: props.version,
        operation_id: props.assignOperation,
        reason: '',
    });
    const transition = useForm({
        version: props.version,
        operation_id: props.operationId,
        status: props.next?.status ?? '',
    });
    return (
        <main className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
            <Head title={order.locationLabel} />
            <header>
                <h1 className="text-3xl font-semibold">
                    {order.locationLabel}
                </h1>
                <p className="mt-2" role="status">
                    {connection}
                </p>
            </header>
            <Card>
                <CardHeader>
                    <CardTitle>{order.status}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {props.contact && (
                        <>
                            <AddressSummary address={props.contact.address} />
                            <p>Telefone: {props.contact.phone}</p>
                        </>
                    )}
                    <p>Entregador: {props.courier ?? 'Ainda não atribuído'}</p>
                    <DiningOrderItems items={order.items} />
                    <p>Taxa de entrega: {order.deliveryFee}</p>
                    <p className="text-xl font-semibold">
                        Total: {order.total}
                    </p>
                    {props.cashUrl && (
                        <Button asChild>
                            <Link href={props.cashUrl}>
                                Conta e pagamento no recebimento
                            </Link>
                        </Button>
                    )}
                    {props.next && (
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                transition.transform((data) => ({
                                    ...data,
                                    version: props.version,
                                    operation_id: props.operationId,
                                    status: props.next?.status ?? '',
                                }));
                                transition.post(props.commandUrl, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            {Object.entries(transition.errors).map(
                                ([key, message]) => (
                                    <InputError key={key} message={message} />
                                ),
                            )}
                            <Button disabled={transition.processing}>
                                {props.next.label}
                            </Button>
                            {props.next.status === 'delivered' && (
                                <p className="text-sm">
                                    Conclua após registrar o pagamento integral
                                    recebido e resolver eventuais devoluções.
                                </p>
                            )}
                        </form>
                    )}
                </CardContent>
            </Card>
            {props.canAssign && (
                <Card>
                    <CardHeader>
                        <CardTitle>Atribuir ou trocar entregador</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                assignment.transform((data) => ({
                                    ...data,
                                    version: props.version,
                                    operation_id: props.assignOperation,
                                }));
                                assignment.post(props.assignUrl, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Label htmlFor="courier">
                                Entregador ativo do restaurante
                            </Label>
                            <select
                                id="courier"
                                required
                                value={assignment.data.courier_id}
                                onChange={(event) =>
                                    assignment.setData(
                                        'courier_id',
                                        event.target.value,
                                    )
                                }
                                className="w-full rounded border bg-background p-2"
                            >
                                <option value="">Selecionar</option>
                                {props.couriers.map((courier) => (
                                    <option key={courier.id} value={courier.id}>
                                        {courier.name}
                                    </option>
                                ))}
                            </select>
                            <Label htmlFor="assignment-reason">Motivo</Label>
                            <Input
                                id="assignment-reason"
                                required
                                minLength={3}
                                maxLength={500}
                                value={assignment.data.reason}
                                onChange={(event) =>
                                    assignment.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                            />
                            {Object.entries(assignment.errors).map(
                                ([key, message]) => (
                                    <InputError key={key} message={message} />
                                ),
                            )}
                            <Button disabled={assignment.processing}>
                                Salvar atribuição
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
            <div className="flex flex-wrap gap-3">
                {props.operationsUrl && (
                    <Button asChild variant="outline">
                        <Link href={props.operationsUrl}>
                            Histórico, correção e cancelamento
                        </Link>
                    </Button>
                )}
                <Button asChild variant="outline">
                    <Link href="/entregas">Voltar às entregas</Link>
                </Button>
            </div>
        </main>
    );
}
