import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

export default function Forbidden() {
    return (
        <>
            <Head title="Acesso não permitido" />
            <main className="flex min-h-screen items-center justify-center p-6">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Acesso não permitido</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Sua conta não tem permissão para esta ação. Consulte
                            o gestor se precisar de outro acesso.
                        </p>
                        <Button asChild>
                            <Link href={dashboard()}>Voltar ao início</Link>
                        </Button>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
