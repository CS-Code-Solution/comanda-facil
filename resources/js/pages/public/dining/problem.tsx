import { Head } from '@inertiajs/react';
export default function DiningProblem({ message }: { message: string }) {
    return (
        <main className="mx-auto max-w-lg space-y-4 p-8">
            <Head title="Acesso ao cardápio" />
            <h1 className="text-2xl font-semibold">
                Não foi possível continuar
            </h1>
            <p role="alert">{message}</p>
            <p className="text-sm text-muted-foreground">
                Para atendimento, procure a equipe do restaurante.
            </p>
        </main>
    );
}
