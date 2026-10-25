import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/pages/dining/index';

type AreaInput = {
    name: string;
    city: string;
    state: string;
    neighborhood: string;
    cep_start: string;
    cep_end: string;
    fee: string;
    minimum: string;
    is_active: boolean;
    version: number | null;
};
function DeliveryAreas({
    areas,
    pagination,
    editing,
    saveUrl,
    serviceUrl,
    receiving,
}: {
    areas: {
        id: number;
        name: string;
        coverage: string;
        fee: string;
        minimum: string;
        active: boolean;
        url: string;
    }[];
    pagination: { previous: string | null; next: string | null };
    editing: (AreaInput & { id: number }) | null;
    saveUrl: string;
    serviceUrl: string;
    receiving: boolean;
}) {
    const form = useForm<AreaInput & { reason: string }>({
        ...(editing ?? {
            name: '',
            city: '',
            state: '',
            neighborhood: '',
            cep_start: '',
            cep_end: '',
            fee: '',
            minimum: '',
            is_active: false,
            version: null,
        }),
        reason: '',
    });
    const service = useForm({ open: !receiving, reason: '' });
    const fields: {
        key: keyof Omit<AreaInput, 'is_active' | 'version'>;
        label: string;
        max: number;
    }[] = [
        { key: 'name', label: 'Nome da área', max: 120 },
        { key: 'city', label: 'Cidade', max: 120 },
        { key: 'state', label: 'UF', max: 2 },
        {
            key: 'neighborhood',
            label: 'Bairro (opcional se houver faixa de CEP)',
            max: 120,
        },
        { key: 'cep_start', label: 'CEP inicial (opcional)', max: 9 },
        { key: 'cep_end', label: 'CEP final (opcional)', max: 9 },
        { key: 'fee', label: 'Taxa de entrega (R$)', max: 16 },
        { key: 'minimum', label: 'Pedido mínimo em itens (R$)', max: 16 },
    ];
    return (
        <main className="space-y-6 p-4 md:p-6">
            <Head title="Cobertura de entrega" />
            <h1 className="text-3xl font-semibold">
                Cobertura, taxas e mínimo
            </h1>
            <p>
                Cadastre os valores do restaurante. Se informar bairro e CEP,
                ambos precisam coincidir. Faixas incluem os dois limites. Regras
                ativas sobrepostas são rejeitadas.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>
                        Recebimento {receiving ? 'aberto' : 'pausado'}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="max-w-xl space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            service.transform((data) => ({
                                ...data,
                                open: !receiving,
                            }));
                            service.post(serviceUrl);
                        }}
                    >
                        <Label htmlFor="service-reason">
                            Motivo para {receiving ? 'pausar' : 'abrir'}{' '}
                            entregas
                        </Label>
                        <Input
                            id="service-reason"
                            required
                            minLength={3}
                            maxLength={500}
                            value={service.data.reason}
                            onChange={(event) =>
                                service.setData('reason', event.target.value)
                            }
                        />
                        {Object.entries(service.errors).map(
                            ([key, message]) => (
                                <InputError key={key} message={message} />
                            ),
                        )}
                        <Button disabled={service.processing}>
                            {receiving ? 'Pausar entregas' : 'Abrir entregas'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
            <Card key={editing?.id ?? 'new'}>
                <CardHeader>
                    <CardTitle>
                        {editing ? 'Editar área' : 'Adicionar área'}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (editing) {
                                form.put(saveUrl);
                            } else {
                                form.post(saveUrl);
                            }
                        }}
                    >
                        {fields.map((field) => (
                            <div className="space-y-2" key={field.key}>
                                <Label htmlFor={'area-' + field.key}>
                                    {field.label}
                                </Label>
                                <Input
                                    id={'area-' + field.key}
                                    maxLength={field.max}
                                    value={form.data[field.key]}
                                    required={
                                        ![
                                            'neighborhood',
                                            'cep_start',
                                            'cep_end',
                                        ].includes(field.key)
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            field.key,
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        ))}
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) =>
                                    form.setData(
                                        'is_active',
                                        event.target.checked,
                                    )
                                }
                            />
                            Área ativa
                        </label>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="area-reason">
                                Motivo da alteração
                            </Label>
                            <Input
                                id="area-reason"
                                required
                                minLength={3}
                                maxLength={500}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-3 sm:col-span-2">
                            <p className="text-sm">
                                Zero é permitido quando taxa ou mínimo forem
                                explicitamente zero. O mínimo considera itens
                                após descontos, antes da taxa. Alterações não
                                mudam entregas já confirmadas.
                            </p>
                            {Object.entries(form.errors).map(
                                ([key, message]) => (
                                    <InputError key={key} message={message} />
                                ),
                            )}
                            <Button disabled={form.processing}>
                                Salvar área
                            </Button>
                            {editing && (
                                <Link
                                    className="ml-4 underline"
                                    href="/entregas/cobertura"
                                >
                                    Adicionar outra área
                                </Link>
                            )}
                        </div>
                    </form>
                </CardContent>
            </Card>
            <section className="grid gap-3 md:grid-cols-2">
                {areas.map((area) => (
                    <Card key={area.id}>
                        <CardContent className="space-y-2 pt-6">
                            <h2 className="font-semibold">
                                {area.name} ·{' '}
                                {area.active ? 'Ativa' : 'Inativa'}
                            </h2>
                            <p>{area.coverage}</p>
                            <p>
                                Taxa {area.fee} · Mínimo {area.minimum}
                            </p>
                            <Link href={area.url} className="underline">
                                Editar / ativar / desativar
                            </Link>
                        </CardContent>
                    </Card>
                ))}
            </section>
            {!areas.length && (
                <p>Nenhuma área cadastrada. O recebimento começa pausado.</p>
            )}
            <Pagination links={pagination} />
            <Link href="/entregas" className="underline">
                Voltar às entregas
            </Link>
        </main>
    );
}

export default function DeliveryAreasPage(
    props: Parameters<typeof DeliveryAreas>[0],
) {
    return <DeliveryAreas key={props.editing?.id ?? 'new'} {...props} />;
}
