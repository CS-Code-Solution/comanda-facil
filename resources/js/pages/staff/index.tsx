import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { StaffAccount } from '@/components/staff-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index } from '@/routes/staff';

type Props = {
    accounts: {
        data: StaffAccount[];
        current_page: number;
        last_page: number;
        total: number;
        previous: string | null;
        next: string | null;
    };
    filters: { q: string };
};

export default function StaffIndex({ accounts, filters }: Props) {
    return (
        <>
            <Head title="Equipe" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Equipe"
                        description="Gerencie contas individuais, papéis e acesso."
                    />
                    <Button asChild>
                        <Link href={create()}>Nova conta</Link>
                    </Button>
                </div>
                <Form {...index.form()} className="flex max-w-xl gap-2">
                    <Input
                        name="q"
                        aria-label="Buscar por nome ou usuário"
                        placeholder="Buscar por nome ou usuário"
                        defaultValue={filters.q}
                        maxLength={100}
                    />
                    <Button type="submit" variant="outline">
                        Buscar
                    </Button>
                </Form>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted">
                            <tr>
                                <th className="p-3">Nome</th>
                                <th className="p-3">Usuário</th>
                                <th className="p-3">Papel</th>
                                <th className="p-3">Acesso</th>
                                <th className="p-3">
                                    <span className="sr-only">Ações</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.data.map((account) => (
                                <tr key={account.id} className="border-t">
                                    <td className="p-3 font-medium">
                                        {account.name}
                                    </td>
                                    <td className="p-3">
                                        {account.username ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        {account.role_label}
                                    </td>
                                    <td className="p-3">
                                        <Badge
                                            variant={
                                                account.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {account.status_label}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={account.edit_url}
                                                aria-label={
                                                    'Gerenciar ' + account.name
                                                }
                                            >
                                                Gerenciar
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {accounts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        Nenhuma conta encontrada.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <p>
                        {accounts.total} conta(s) · Página{' '}
                        {accounts.current_page} de {accounts.last_page}
                    </p>
                    <div className="flex gap-2">
                        {accounts.previous && (
                            <Button asChild variant="outline">
                                <Link href={accounts.previous}>Anterior</Link>
                            </Button>
                        )}
                        {accounts.next && (
                            <Button asChild variant="outline">
                                <Link href={accounts.next}>Próxima</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
StaffIndex.layout = { breadcrumbs: [{ title: 'Equipe', href: index() }] };
