import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useOperationsRealtime } from '@/hooks/use-operations-realtime';
import { Pagination } from '@/pages/dining/index';
type Job = {
    id: number;
    reference: string;
    table: number | null;
    locationLabel?: string;
    revision: number;
    copy: number;
    target: string;
    kind: string;
    status: string;
    stale: boolean;
    url: string;
};
export const printStatus: Record<string, string> = {
    pending: 'Aguardando impressão',
    printing: 'Aguardando resultado do operador',
    printed: 'Impresso (declarado pelo operador)',
    failed: 'Falha registrada',
    superseded: 'Substituído por revisão mais recente',
    created: 'Comprovante gerado',
    claim: 'Impressão assumida',
    reprint: 'Nova cópia solicitada',
    lease_expired: 'Impressão interrompida',
};
export const printKind: Record<string, string> = {
    original: 'Pedido',
    amendment: 'CORREÇÃO',
    cancellation: 'CANCELAMENTO',
};
export default function Printing({
    jobs,
    pagination,
    showAll,
}: {
    jobs: Job[];
    pagination: { previous: string | null; next: string | null };
    showAll: boolean;
}) {
    const connection = useOperationsRealtime('operations.printing');
    return (
        <main className="space-y-5 p-4 md:p-6">
            <Head title="Comprovantes" />
            <h1 className="text-3xl font-semibold">Comprovantes</h1>
            <p className="text-sm" role="status">
                {connection}
            </p>
            <p>
                Impressão pelo navegador. Abra o comprovante, assuma a impressão
                e registre se saiu em papel ou falhou.
            </p>
            <Button variant="outline" asChild>
                <Link href={showAll ? '/impressoes' : '/impressoes?all=1'}>
                    {showAll
                        ? 'Ver pendentes e falhas'
                        : 'Ver todo o histórico'}
                </Link>
            </Button>
            {jobs.length === 0 && <p>Nenhum comprovante neste filtro.</p>}
            {jobs.map((job) => (
                <Card key={job.id}>
                    <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                        <div>
                            <h2 className="font-semibold">
                                {job.target} ·{' '}
                                {job.locationLabel ?? 'Mesa ' + job.table} ·{' '}
                                {printKind[job.kind]}
                            </h2>
                            <p>
                                Revisão {job.revision} · Cópia {job.copy} ·{' '}
                                {printStatus[job.status]}
                            </p>
                            {job.stale && (
                                <p className="font-semibold text-destructive">
                                    Revisão antiga; consulte a correção ou
                                    cancelamento atual.
                                </p>
                            )}
                        </div>
                        <Button asChild variant="outline">
                            <Link href={job.url}>
                                Abrir comprovante #{job.id}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            ))}
            <Pagination links={pagination} />
        </main>
    );
}
