import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
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
                        <Badge variant="secondary">História 00-A</Badge>
                        <CardTitle className="text-2xl">
                            Comanda Fácil
                        </CardTitle>
                        <CardDescription>
                            Base técnica do projeto instalada.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            Laravel, Inertia, React, TypeScript e shadcn/ui
                            estão prontos para o desenvolvimento das próximas
                            histórias.
                        </p>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}
