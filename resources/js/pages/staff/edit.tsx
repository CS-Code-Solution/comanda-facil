import { Form, Head, Link } from '@inertiajs/react';
import StaffAccountController from '@/actions/App/Http/Controllers/StaffAccountController';
import Heading from '@/components/heading';
import {
    IdentityFields,
    PinFields,
    type AuditEvent,
    type RoleOption,
    type StaffAccount,
} from '@/components/staff-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { index } from '@/routes/staff';

type Props = {
    account: StaffAccount;
    roles: RoleOption[];
    pinLength: number;
    canChangeRole: boolean;
    canChangeStatus: boolean;
    sessionActionLabel: string;
    events: AuditEvent[];
};
const secrets = ['pin', 'pin_confirmation', 'current_pin'];

export default function EditStaff({
    account,
    roles,
    pinLength,
    canChangeRole,
    canChangeStatus,
    sessionActionLabel,
    events,
}: Props) {
    return (
        <>
            <Head title={'Conta de ' + account.name} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={account.name}
                        description={
                            'Usuário: ' + (account.username ?? 'não definido')
                        }
                    />
                    <div className="flex items-center gap-3">
                        <Badge variant="outline">{account.status_label}</Badge>
                        <Button variant="outline" asChild>
                            <Link href={index()}>Voltar à equipe</Link>
                        </Button>
                    </div>
                </div>
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Cadastro e papel</CardTitle>
                            <CardDescription>
                                Alterar usuário ou papel encerra as sessões
                                anteriores.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                key={
                                    account.name +
                                    ':' +
                                    account.username +
                                    ':' +
                                    account.role
                                }
                                {...StaffAccountController.update.form(
                                    account.id,
                                )}
                                options={{
                                    preserveScroll: true,
                                }}
                                errorBag="identity"
                                resetOnError={secrets}
                                resetOnSuccess={secrets}
                                className="space-y-5"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <IdentityFields
                                            roles={roles}
                                            errors={errors}
                                            account={account}
                                            canChangeRole={canChangeRole}
                                        />
                                        <PinFields
                                            errors={errors}
                                            pinLength={pinLength}
                                        />
                                        <Button disabled={processing}>
                                            Salvar cadastro
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Redefinir PIN</CardTitle>
                            <CardDescription>
                                Informe o novo PIN da pessoa e confirme com o
                                seu PIN atual.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...StaffAccountController.resetPin.form(
                                    account.id,
                                )}
                                options={{
                                    preserveScroll: true,
                                }}
                                errorBag="resetPin"
                                resetOnError={secrets}
                                resetOnSuccess={secrets}
                                className="space-y-5"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <PinFields
                                            errors={errors}
                                            pinLength={pinLength}
                                            includeNew
                                        />
                                        <Button disabled={processing}>
                                            Redefinir PIN
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Acesso à conta</CardTitle>
                            <CardDescription>
                                {canChangeStatus
                                    ? 'Desativar impede novos logins e revoga as sessões existentes.'
                                    : 'Outro gestor deve alterar o acesso da sua conta.'}
                            </CardDescription>
                        </CardHeader>
                        {canChangeStatus && (
                            <CardContent>
                                <Form
                                    {...(account.is_active
                                        ? StaffAccountController.disable.form(
                                              account.id,
                                          )
                                        : StaffAccountController.activate.form(
                                              account.id,
                                          ))}
                                    options={{
                                        preserveScroll: true,
                                    }}
                                    errorBag="status"
                                    resetOnError={secrets}
                                    resetOnSuccess={secrets}
                                    className="space-y-5"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <PinFields
                                                errors={errors}
                                                pinLength={pinLength}
                                            />
                                            <Button
                                                variant={
                                                    account.is_active
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                                disabled={processing}
                                            >
                                                {account.is_active
                                                    ? 'Desativar conta'
                                                    : 'Ativar conta'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        )}
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Sessões</CardTitle>
                            <CardDescription>
                                Os dispositivos precisarão entrar novamente no
                                próximo acesso. A sessão que você está usando é
                                mantida.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...StaffAccountController.revokeSessions.form(
                                    account.id,
                                )}
                                options={{
                                    preserveScroll: true,
                                }}
                                errorBag="sessions"
                                resetOnError={secrets}
                                resetOnSuccess={secrets}
                                className="space-y-5"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <PinFields
                                            errors={errors}
                                            pinLength={pinLength}
                                        />
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            {sessionActionLabel}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Histórico de acesso</CardTitle>
                        <CardDescription>
                            As últimas 20 alterações desta conta.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol className="divide-y">
                            {events.map((event) => (
                                <li
                                    key={event.id}
                                    className="space-y-2 py-4 first:pt-0"
                                >
                                    <p className="text-sm font-medium">
                                        {event.action}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {event.actor} ·{' '}
                                        {new Date(
                                            event.occurred_at,
                                        ).toLocaleString('pt-BR')}
                                    </p>
                                    {event.changes.map((change) => (
                                        <p
                                            key={change.field}
                                            className="text-sm"
                                        >
                                            {change.field}: {change.before} →{' '}
                                            {change.after}
                                        </p>
                                    ))}
                                </li>
                            ))}
                            {events.length === 0 && (
                                <li className="text-sm text-muted-foreground">
                                    Nenhuma alteração registrada.
                                </li>
                            )}
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
EditStaff.layout = {
    breadcrumbs: [
        { title: 'Equipe', href: index() },
        { title: 'Gerenciar conta', href: '#' },
    ],
};
