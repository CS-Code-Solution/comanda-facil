import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function Welcome() {
    return (
        <>
            <Head title="Início" />
            <main className="flex min-h-screen items-center justify-center bg-background px-4 py-12">
                <Card className="w-full max-w-xl">
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Comanda Fácil
                        </CardTitle>
                        <CardDescription>
                            Faça seu pedido para retirada ou acesse o painel da
                            equipe.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-3">
                            <Button asChild>
                                <Link href="/retirada">
                                    Pedir para retirada
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href="/login">Acesso da equipe</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
