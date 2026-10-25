import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Props = {
    products: {
        id: number;
        name: string;
        description: string | null;
        category: string;
        available: boolean;
        photo: string | null;
        url: string;
    }[];
    categories: { id: number; name: string }[];
    filters: { q?: string; category_id?: string };
    pagination: { previous: string | null; next: string | null };
    adminUrl: string | null;
};
export default function CatalogIndex({
    products,
    categories,
    filters,
    pagination,
    adminUrl,
}: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const [category, setCategory] = useState(filters.category_id ?? '');
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Catálogo" />
            <div className="flex flex-wrap justify-between gap-4">
                <Heading
                    title="Catálogo"
                    description="Consulte apresentações e calcule o preço atual antes de lançar pedidos."
                />
                {adminUrl && (
                    <Button variant="outline" asChild>
                        <Link href={adminUrl}>Administrar catálogo</Link>
                    </Button>
                )}
            </div>
            <form
                className="flex flex-wrap gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get('/catalogo', {
                        q: search,
                        category_id: category,
                    });
                }}
            >
                <Input
                    className="w-auto grow"
                    placeholder="Buscar produto"
                    aria-label="Buscar produto"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
                <select
                    aria-label="Categoria"
                    className="h-10 rounded-md border bg-background px-3"
                    value={category}
                    onChange={(event) => setCategory(event.target.value)}
                >
                    <option value="">Todas as categorias</option>
                    {categories.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.name}
                        </option>
                    ))}
                </select>
                <Button>Buscar</Button>
            </form>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {products.map((product) => (
                    <Card key={product.id}>
                        {product.photo && (
                            <img
                                src={product.photo}
                                className="h-44 w-full rounded-t-lg object-cover"
                                alt={product.name}
                            />
                        )}
                        <CardHeader>
                            <p className="text-xs text-muted-foreground">
                                {product.category}
                            </p>
                            <CardTitle>{product.name}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="line-clamp-3 text-sm">
                                {product.description}
                            </p>
                            <Badge variant="outline">
                                {product.available
                                    ? 'Disponível'
                                    : 'Indisponível'}
                            </Badge>
                            <div>
                                <Button variant="secondary" asChild>
                                    <Link href={product.url}>
                                        Ver apresentações
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
            {products.length === 0 && (
                <p className="py-10 text-muted-foreground">
                    Nenhum produto publicado encontrado.
                </p>
            )}
            <div className="flex gap-3">
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
CatalogIndex.layout = {
    breadcrumbs: [{ title: 'Catálogo', href: '/catalogo' }],
};
