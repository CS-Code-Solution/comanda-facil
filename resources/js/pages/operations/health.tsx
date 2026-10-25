import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/pages/dining/index';

type Check = {
    name: string;
    label: string;
    passed: boolean;
    version: number;
    evidence: string | null;
    checkedAt: string | null;
    url: string;
};
function Evidence({ check }: { check: Check }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        version: check.version,
        passed: false,
        evidence: '',
        performed: false,
    });
    return (
        <Card>
            <CardHeader>
                <CardTitle>{check.label}</CardTitle>
                <p>{check.passed ? 'Conferido' : 'Pendente'}</p>
            </CardHeader>
            <CardContent className="space-y-3">
                {check.evidence && (
                    <p className="text-sm whitespace-pre-wrap">
                        {check.evidence}
                    </p>
                )}
                {check.checkedAt && (
                    <p className="text-sm">
                        Registrado em{' '}
                        {new Date(check.checkedAt).toLocaleString('pt-BR')}
                    </p>
                )}
                <Button
                    variant="outline"
                    onClick={() => setOpen(!open)}
                    aria-expanded={open}
                >
                    Registrar evidência
                </Button>
                {open && (
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.transform((data) => ({
                                ...data,
                                version: check.version,
                            }));
                            form.post(check.url, { preserveScroll: true });
                        }}
                    >
                        <Label htmlFor={check.name}>
                            Resultado e evidência (sem dados pessoais ou
                            segredos)
                        </Label>
                        <textarea
                            id={check.name}
                            className="min-h-28 w-full rounded border bg-background p-3"
                            minLength={10}
                            maxLength={1500}
                            required
                            value={form.data.evidence}
                            onChange={(event) =>
                                form.setData('evidence', event.target.value)
                            }
                        />
                        <label className="flex items-start gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.passed}
                                onChange={(event) =>
                                    form.setData('passed', event.target.checked)
                                }
                            />
                            Teste concluído com sucesso
                        </label>
                        <label className="flex items-start gap-2">
                            <input
                                type="checkbox"
                                required
                                checked={form.data.performed}
                                onChange={(event) =>
                                    form.setData(
                                        'performed',
                                        event.target.checked,
                                    )
                                }
                            />
                            Declaro que esta conferência foi realizada e
                            descrevi o resultado real.
                        </label>
                        {Object.entries(form.errors).map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                        <Button disabled={form.processing}>
                            Salvar conferência
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

export default function OperationalHealth({
    status,
    checks,
    events,
    pagination,
}: {
    status: {
        ready: boolean;
        checks: {
            key: string;
            label: string;
            status: string;
            detail: string;
        }[];
        stats: Record<string, number>;
        backup: { created_at: string; bytes: number; photos: number } | null;
    };
    checks: Check[];
    events: {
        action: string;
        details: Record<string, unknown>;
        at: string;
        actor: string;
    }[];
    pagination: { previous: string | null; next: string | null };
}) {
    return (
        <main className="space-y-6 p-4 md:p-6">
            <Head title="Operação e piloto" />
            <header className="space-y-2">
                <h1 className="text-3xl font-semibold">Operação e piloto</h1>
                <p>
                    Verifique os serviços e registre somente testes realizados.
                    A aprovação técnica não substitui a impressão em papel e o
                    aceite do restaurante.
                </p>
                <Button asChild variant="outline">
                    <Link href="/admin/operacao">Atualizar diagnóstico</Link>
                </Button>
            </header>
            <Card>
                <CardHeader>
                    <CardTitle>
                        {status.ready
                            ? 'Verificações técnicas aprovadas'
                            : 'Há ações técnicas pendentes'}
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-2">
                    {status.checks.map((check) => (
                        <div
                            key={check.key}
                            className={
                                'space-y-2 rounded border p-3 ' +
                                (check.status === 'pass'
                                    ? ''
                                    : 'border-amber-500')
                            }
                        >
                            <h2 className="font-semibold">
                                {check.label} ·{' '}
                                {check.status === 'pass' ? 'OK' : 'Revisar'}
                            </h2>
                            <p className="text-sm">{check.detail}</p>
                        </div>
                    ))}
                    <p className="text-sm">
                        Outbox pendente: {status.stats.outboxPending ?? 0} ·
                        Atrasada: {status.stats.outboxLate ?? 0} · Impressões
                        com falha: {status.stats.printFailed ?? 0}
                    </p>
                </CardContent>
            </Card>
            <div className="flex flex-wrap gap-3">
                <Button asChild variant="outline">
                    <Link href="/impressoes">Fila de impressão</Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href="/entregas/cobertura">Cobertura de entrega</Link>
                </Button>
            </div>
            <section className="space-y-3">
                <h2 className="text-2xl font-semibold">
                    Conferência do restaurante
                </h2>
                <p>
                    Modelo da térmica, largura do papel, aparelhos e rede
                    precisam de teste real. Sem expurgo automático enquanto não
                    houver prazo aprovado.
                </p>
                <div className="grid items-start gap-4 lg:grid-cols-2">
                    {checks.map((check) => (
                        <Evidence
                            key={check.name + '-' + check.version}
                            check={check}
                        />
                    ))}
                </div>
            </section>
            <section className="space-y-3">
                <h2 className="text-2xl font-semibold">
                    Histórico operacional
                </h2>
                {events.map((event, index) => (
                    <div key={index} className="rounded border p-3 text-sm">
                        <p>
                            {event.action} · {event.actor} ·{' '}
                            {new Date(event.at).toLocaleString('pt-BR')}
                        </p>
                        <pre className="mt-2 break-all whitespace-pre-wrap">
                            {JSON.stringify(event.details, null, 2)}
                        </pre>
                    </div>
                ))}
                <Pagination links={pagination} />
            </section>
        </main>
    );
}
