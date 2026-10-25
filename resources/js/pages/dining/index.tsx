import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
type Links = { previous: string | null; next: string | null };
type Props = {
    tables: {
        id: number;
        number: number;
        label: string | null;
        active: boolean;
        blocked: boolean;
        open: boolean;
        total: string;
        recentQr: number;
        url: string;
    }[];
    tablesLinks: Links;
    calls: {
        id: number;
        table: number;
        status: string;
        claimant: string | null;
        createdAt: string;
        claimUrl: string | null;
        completeUrl: string | null;
    }[];
    callsLinks: Links;
    alerts: { id: number; table: number; message: string; ackUrl: string }[];
    alertsLinks: Links;
    receiving: boolean;
    canManage: boolean;
};
export function Pagination({ links }: { links: Links }) {
    return (
        <div className="mt-4 flex gap-3">
            {links.previous && (
                <Button variant="outline" asChild>
                    <Link href={links.previous} preserveScroll>
                        Anterior
                    </Link>
                </Button>
            )}
            {links.next && (
                <Button variant="outline" asChild>
                    <Link href={links.next} preserveScroll>
                        Próxima
                    </Link>
                </Button>
            )}
        </div>
    );
}
export default function DiningIndex({
    tables,
    tablesLinks,
    calls,
    callsLinks,
    alerts,
    alertsLinks,
    receiving,
    canManage,
}: Props) {
    useOperationsRealtime('operations.dining');
    usePoll(5000, {
        only: [
            'tables',
            'tablesLinks',
            'calls',
            'callsLinks',
            'alerts',
            'alertsLinks',
            'receiving',
        ],
    });
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Salão" />
            <div className="flex flex-wrap justify-between gap-4">
                <Heading
                    title="Salão"
                    description="Mesas, pedidos e chamados. Atualização automática a cada cinco segundos."
                />
                <Badge variant={receiving ? 'default' : 'outline'}>
                    {receiving ? 'Recebendo pedidos' : 'Recebimento pausado'}
                </Badge>
            </div>
            {alerts.length > 0 && (
                <Card className="border-amber-400">
                    <CardHeader>
                        <CardTitle>Alertas de reabertura</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {alerts.map((alert) => (
                                <li
                                    className="flex flex-wrap justify-between gap-3 py-3"
                                    key={alert.id}
                                >
                                    <p>
                                        Mesa {alert.table} · {alert.message}
                                    </p>
                                    <Form
                                        action={alert.ackUrl}
                                        method="post"
                                        options={{ preserveScroll: true }}
                                    >
                                        <Button variant="outline">
                                            Ciente
                                        </Button>
                                    </Form>
                                </li>
                            ))}
                        </ul>
                        <Pagination links={alertsLinks} />
                    </CardContent>
                </Card>
            )}
            <Card>
                <CardHeader>
                    <CardTitle>Chamados</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul className="divide-y">
                        {calls.map((call) => (
                            <li
                                className="flex flex-wrap items-center justify-between gap-3 py-3"
                                key={call.id}
                            >
                                <div>
                                    <p className="font-medium">
                                        Mesa {call.table} · {call.status}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {new Date(
                                            call.createdAt,
                                        ).toLocaleTimeString('pt-BR')}
                                        {call.claimant && ' · ' + call.claimant}
                                    </p>
                                </div>
                                {(call.claimUrl || call.completeUrl) && (
                                    <Form
                                        action={
                                            call.claimUrl ??
                                            call.completeUrl ??
                                            ''
                                        }
                                        method="post"
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <Button disabled={processing}>
                                                    {call.claimUrl
                                                        ? 'Assumir'
                                                        : 'Concluir atendimento'}
                                                </Button>
                                                <InputError
                                                    message={errors.call}
                                                />
                                            </>
                                        )}
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                    {calls.length === 0 && (
                        <p className="text-muted-foreground">
                            Nenhum chamado aguardando atendimento.
                        </p>
                    )}
                    <Pagination links={callsLinks} />
                </CardContent>
            </Card>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {tables.map((table) => (
                    <Card key={table.id}>
                        <CardHeader>
                            <CardTitle>Mesa {table.number}</CardTitle>
                            <p className="text-sm">{table.label}</p>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Badge variant="outline">
                                {!table.active
                                    ? 'Inativa'
                                    : table.blocked
                                      ? 'Bloqueada'
                                      : table.open
                                        ? 'Em atendimento'
                                        : 'Sem atendimento'}
                            </Badge>
                            <p className="font-medium">{table.total}</p>
                            <p className="text-xs text-muted-foreground">
                                {table.recentQr} pedido(s) via QR nos últimos 5
                                minutos
                            </p>
                            <Button asChild variant="secondary">
                                <Link href={table.url}>Abrir mesa</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ))}
            </div>
            {tables.length === 0 && (
                <p>
                    Nenhuma mesa cadastrada. O gestor precisa cadastrar as mesas
                    do restaurante.
                </p>
            )}
            <Pagination links={tablesLinks} />
            {canManage && (
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Cadastrar mesa</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/salao/mesas"
                                method="post"
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="number">
                                                Número da mesa
                                            </Label>
                                            <Input
                                                id="number"
                                                name="number"
                                                type="number"
                                                min="1"
                                                max="9999"
                                                required
                                            />
                                            <InputError
                                                message={errors.number}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="label">
                                                Identificação opcional
                                            </Label>
                                            <Input
                                                id="label"
                                                name="label"
                                                maxLength={80}
                                            />
                                            <InputError
                                                message={errors.label}
                                            />
                                        </div>
                                        <Button disabled={processing}>
                                            Cadastrar mesa
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recebimento do salão</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/salao/servico"
                                method="post"
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="open"
                                            value={receiving ? '0' : '1'}
                                        />
                                        <Label htmlFor="reason">
                                            Motivo da alteração
                                        </Label>
                                        <Input
                                            name="reason"
                                            id="reason"
                                            minLength={3}
                                            maxLength={500}
                                            required
                                        />
                                        <InputError message={errors.reason} />
                                        <Button
                                            disabled={processing}
                                            variant={
                                                receiving
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                        >
                                            {receiving
                                                ? 'Pausar recebimento'
                                                : 'Abrir recebimento'}
                                        </Button>
                                        <p className="text-xs text-muted-foreground">
                                            Pausar impede novos pedidos e
                                            chamados. Os registros existentes
                                            são preservados.
                                        </p>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
            )}
        </div>
    );
}
DiningIndex.layout = { breadcrumbs: [{ title: 'Salão', href: '/salao' }] };
