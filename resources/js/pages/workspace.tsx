import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

export default function Workspace({
    title,
    printsUrl,
}: {
    title: string;
    printsUrl: string | null;
}) {
    const { auth } = usePage().props;
    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title={title}
                    description={'Conta individual de ' + auth.user.name}
                />
                <Card>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            Seu acesso a esta área está habilitado. As funções
                            operacionais serão disponibilizadas nas próximas
                            entregas.
                        </p>
                    </CardContent>
                </Card>
                {printsUrl && (
                    <Button asChild>
                        <Link href={printsUrl}>Comprovantes do caixa</Link>
                    </Button>
                )}
                <Button asChild variant="outline">
                    <Link href={dashboard()}>Voltar ao início</Link>
                </Button>
            </div>
        </>
    );
}
