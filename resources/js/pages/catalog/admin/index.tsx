import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Props = {
    title: string;
    tabs: { label: string; url: string; active: boolean }[];
    createUrl: string;
    searchUrl: string;
    rows: {
        id: number;
        name: string;
        detail: string;
        status: string;
        url: string;
    }[];
    filters: { q?: string; product_id?: string; group_id?: string };
    pagination: {
        previous: string | null;
        next: string | null;
        current: number;
        last: number;
        total: number;
    };
};

export default function CatalogAdminIndex({
    title,
    tabs,
    createUrl,
    searchUrl,
    rows,
    filters,
    pagination,
}: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={'Catálogo · ' + title} />
            <div className="flex flex-wrap justify-between gap-4">
                <Heading
                    title={title}
                    description="Cadastros do cardápio e disponibilidade manual."
                />
                <Button asChild>
                    <Link href={createUrl}>Novo cadastro</Link>
                </Button>
            </div>
            <nav
                aria-label="Cadastros do catálogo"
                className="flex flex-wrap gap-2"
            >
                {tabs.map((tab) => (
                    <Button
                        key={tab.url}
                        variant={tab.active ? 'default' : 'outline'}
                        asChild
                    >
                        <Link href={tab.url}>{tab.label}</Link>
                    </Button>
                ))}
            </nav>
            <form
                className="flex gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(searchUrl, { ...filters, q: search });
                }}
            >
                <Input
                    aria-label="Buscar pelo nome"
                    placeholder="Buscar pelo nome"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <Button variant="secondary">Buscar</Button>
            </form>
            <Card>
                <CardContent className="divide-y">
                    {rows.map((row) => (
                        <div
                            key={row.id}
                            className="flex flex-wrap items-center justify-between gap-3 py-4"
                        >
                            <div>
                                <Link
                                    className="font-medium underline underline-offset-4"
                                    href={row.url}
                                >
                                    {row.name}
                                </Link>
                                <p className="text-sm text-muted-foreground">
                                    {row.detail}
                                </p>
                            </div>
                            <Badge variant="outline">{row.status}</Badge>
                        </div>
                    ))}
                    {rows.length === 0 && (
                        <p className="py-6 text-muted-foreground">
                            Nenhum cadastro encontrado. Crie um cadastro ou
                            importe a amostra conferida pelo comando
                            documentado.
                        </p>
                    )}
                </CardContent>
            </Card>
            <div className="flex items-center gap-4">
                <span className="text-sm">
                    {pagination.total} registros · página {pagination.current}{' '}
                    de {pagination.last}
                </span>
                {pagination.previous && (
                    <Button variant="outline" asChild>
                        <Link href={pagination.previous}>Anterior</Link>
                    </Button>
                )}
                {pagination.next && (
                    <Button variant="outline" asChild>
                        <Link href={pagination.next}>Próxima</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
CatalogAdminIndex.layout = {
    breadcrumbs: [{ title: 'Administração do catálogo', href: '#' }],
};
