import { Form, Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Part = { name: string; quantity: number | string };
type Value = string | number | boolean | null | number[] | Part[];
type Field = {
    name: string;
    label: string;
    type: string;
    value: Value;
    hint?: string;
    options?: { value: string | number; label: string }[];
    disabled?: boolean;
    depends_on?: string;
    when?: string;
};
type Props = {
    title: string;
    editing: boolean;
    fields: Field[];
    submitUrl: string;
    backUrl: string;
    links: { label: string; url: string }[];
    photo: { url: string | null; uploadUrl: string; deleteUrl: string } | null;
    events: {
        id: number;
        action: string;
        actor: string;
        at: string;
        priceBefore: string | null;
        priceAfter: string | null;
    }[];
};
const selectClass = 'h-10 w-full rounded-md border bg-background px-3 text-sm';

export default function CatalogForm({
    title,
    editing,
    fields,
    submitUrl,
    backUrl,
    links,
    photo,
    events,
}: Props) {
    const form = useForm<Record<string, Value>>(
        Object.fromEntries(fields.map((field) => [field.name, field.value])),
    );
    function submit() {
        form.transform((data) =>
            Object.fromEntries(
                fields.map((field) => [
                    field.name,
                    field.depends_on && data[field.depends_on] !== field.when
                        ? field.type === 'composition'
                            ? []
                            : null
                        : data[field.name],
                ]),
            ),
        );
        if (editing) form.put(submitUrl, { preserveScroll: true });
        else form.post(submitUrl, { preserveScroll: true });
    }
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={title} />
            <div className="flex flex-wrap justify-between gap-4">
                <Heading
                    title={(editing ? 'Editar · ' : 'Novo · ') + title}
                    description="Confira as informações antes de publicar. Desmarque a disponibilidade para pausar a venda."
                />
                <Button variant="outline" asChild>
                    <Link href={backUrl}>Voltar</Link>
                </Button>
            </div>
            <div className="flex flex-wrap gap-3">
                {links.map((link) => (
                    <Button key={link.url} variant="secondary" asChild>
                        <Link href={link.url}>{link.label}</Link>
                    </Button>
                ))}
            </div>
            <Card>
                <CardContent>
                    <form
                        className="grid gap-5 pt-6 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                    >
                        {fields.map((field) => {
                            if (
                                field.depends_on &&
                                form.data[field.depends_on] !== field.when
                            )
                                return null;
                            const value = form.data[field.name];
                            return (
                                <div
                                    key={field.name}
                                    className={
                                        [
                                            'textarea',
                                            'composition',
                                            'multiselect',
                                        ].includes(field.type)
                                            ? 'space-y-2 md:col-span-2'
                                            : 'space-y-2'
                                    }
                                >
                                    <Label htmlFor={field.name}>
                                        {field.label}
                                    </Label>
                                    {field.type === 'checkbox' ? (
                                        <div>
                                            <input
                                                id={field.name}
                                                type="checkbox"
                                                className="size-5 accent-primary"
                                                checked={Boolean(value)}
                                                onChange={(event) =>
                                                    form.setData(
                                                        field.name,
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                        </div>
                                    ) : field.type === 'select' ? (
                                        <select
                                            id={field.name}
                                            className={selectClass}
                                            value={
                                                typeof value === 'string' ||
                                                typeof value === 'number'
                                                    ? value
                                                    : ''
                                            }
                                            disabled={field.disabled}
                                            onChange={(event) =>
                                                form.setData(
                                                    field.name,
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">Selecione</option>
                                            {field.options?.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    ) : field.type === 'textarea' ? (
                                        <textarea
                                            id={field.name}
                                            className="min-h-28 w-full rounded-md border bg-background p-3"
                                            value={
                                                typeof value === 'string' ||
                                                typeof value === 'number'
                                                    ? value
                                                    : ''
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    field.name,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    ) : field.type === 'multiselect' ? (
                                        <div className="flex flex-wrap gap-4">
                                            {field.options?.map((option) => (
                                                <label
                                                    className="flex items-center gap-2 text-sm"
                                                    key={option.value}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={(
                                                            value as number[]
                                                        ).includes(
                                                            Number(
                                                                option.value,
                                                            ),
                                                        )}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                field.name,
                                                                event.target
                                                                    .checked
                                                                    ? [
                                                                          ...(value as number[]),
                                                                          Number(
                                                                              option.value,
                                                                          ),
                                                                      ]
                                                                    : (
                                                                          value as number[]
                                                                      ).filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              Number(
                                                                                  option.value,
                                                                              ),
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                    {option.label}
                                                </label>
                                            ))}
                                            {field.options?.length === 0 && (
                                                <p className="text-sm text-muted-foreground">
                                                    Cadastre os grupos primeiro.
                                                </p>
                                            )}
                                        </div>
                                    ) : field.type === 'composition' ? (
                                        <div className="space-y-3">
                                            {(value as Part[]).map(
                                                (part, index) => (
                                                    <div
                                                        className="flex gap-2"
                                                        key={index}
                                                    >
                                                        <Input
                                                            aria-label={
                                                                'Componente ' +
                                                                (index + 1)
                                                            }
                                                            placeholder="Nome do componente"
                                                            value={part.name}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    field.name,
                                                                    (
                                                                        value as Part[]
                                                                    ).map(
                                                                        (
                                                                            item,
                                                                            i,
                                                                        ) =>
                                                                            i ===
                                                                            index
                                                                                ? {
                                                                                      ...item,
                                                                                      name: event
                                                                                          .target
                                                                                          .value,
                                                                                  }
                                                                                : item,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        <Input
                                                            className="w-24"
                                                            aria-label={
                                                                'Quantidade do componente ' +
                                                                (index + 1)
                                                            }
                                                            type="number"
                                                            min="1"
                                                            value={
                                                                part.quantity
                                                            }
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    field.name,
                                                                    (
                                                                        value as Part[]
                                                                    ).map(
                                                                        (
                                                                            item,
                                                                            i,
                                                                        ) =>
                                                                            i ===
                                                                            index
                                                                                ? {
                                                                                      ...item,
                                                                                      quantity:
                                                                                          event
                                                                                              .target
                                                                                              .value,
                                                                                  }
                                                                                : item,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() =>
                                                                form.setData(
                                                                    field.name,
                                                                    (
                                                                        value as Part[]
                                                                    ).filter(
                                                                        (
                                                                            _,
                                                                            i,
                                                                        ) =>
                                                                            i !==
                                                                            index,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            Remover
                                                        </Button>
                                                    </div>
                                                ),
                                            )}
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                onClick={() =>
                                                    form.setData(field.name, [
                                                        ...(value as Part[]),
                                                        {
                                                            name: '',
                                                            quantity: 1,
                                                        },
                                                    ])
                                                }
                                            >
                                                Adicionar componente
                                            </Button>
                                        </div>
                                    ) : (
                                        <Input
                                            id={field.name}
                                            type={field.type}
                                            disabled={field.disabled}
                                            value={
                                                typeof value === 'string' ||
                                                typeof value === 'number'
                                                    ? value
                                                    : ''
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    field.name,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                    {field.hint && (
                                        <p className="text-xs text-muted-foreground">
                                            {field.hint}
                                        </p>
                                    )}
                                    <InputError
                                        message={form.errors[field.name]}
                                    />
                                    {Object.entries(form.errors)
                                        .filter(([key]) =>
                                            key.startsWith(field.name + '.'),
                                        )
                                        .map(([key, message]) => (
                                            <InputError
                                                key={key}
                                                message={message}
                                            />
                                        ))}
                                </div>
                            );
                        })}
                        <div className="md:col-span-2">
                            <Button disabled={form.processing}>
                                Salvar cadastro
                            </Button>
                            <span aria-live="polite" className="ml-3 text-sm">
                                {form.recentlySuccessful ? 'Salvo.' : ''}
                            </span>
                        </div>
                    </form>
                </CardContent>
            </Card>
            {photo && (
                <Card>
                    <CardHeader>
                        <CardTitle>Foto do produto</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {photo.url && (
                            <img
                                src={photo.url}
                                className="max-h-60 rounded-lg object-contain"
                                alt="Foto atual do produto"
                            />
                        )}
                        <Form
                            action={photo.uploadUrl}
                            method="post"
                            encType="multipart/form-data"
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="space-y-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Label htmlFor="photo">
                                        JPEG, PNG ou WebP · até 5 MB e 6000 ×
                                        6000 px
                                    </Label>
                                    <Input
                                        id="photo"
                                        name="photo"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        required
                                    />
                                    <InputError message={errors.photo} />
                                    <Button disabled={processing}>
                                        Enviar foto
                                    </Button>
                                </>
                            )}
                        </Form>
                        {photo.url && (
                            <Form
                                action={photo.deleteUrl}
                                method="delete"
                                options={{ preserveScroll: true }}
                            >
                                <Button variant="outline">Remover foto</Button>
                            </Form>
                        )}
                    </CardContent>
                </Card>
            )}
            {editing && (
                <Card>
                    <CardHeader>
                        <CardTitle>Últimas alterações</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="divide-y">
                            {events.map((event) => (
                                <li className="py-3 text-sm" key={event.id}>
                                    <p>
                                        {event.action} · {event.actor}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {new Date(event.at).toLocaleString(
                                            'pt-BR',
                                        )}
                                        {event.priceBefore &&
                                            event.priceAfter && (
                                                <>
                                                    {' '}
                                                    · {event.priceBefore} →{' '}
                                                    {event.priceAfter}
                                                </>
                                            )}
                                    </p>
                                </li>
                            ))}
                            {events.length === 0 && (
                                <li>Nenhuma alteração registrada.</li>
                            )}
                        </ol>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
CatalogForm.layout = {
    breadcrumbs: [
        { title: 'Administração do catálogo', href: '/admin/catalogo' },
        { title: 'Cadastro', href: '#' },
    ],
};
