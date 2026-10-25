import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    product: {
        name: string;
        description: string | null;
        photo: string | null;
        isPizza: boolean;
        composition: { name: string; quantity: number }[];
    };
    variants: { id: number; name: string; price: string }[];
    secondVariants: { id: number; name: string }[];
    groups: {
        id: number;
        name: string;
        hint: string;
        options: { id: number; name: string; price: string; max: number }[];
    }[];
    canManualDiscount: boolean;
    quoteUrl: string;
    quote: {
        name: string;
        variant_name: string;
        quantity: number;
        gross_label: string;
        discount_label: string;
        total_label: string;
        unit_label: string;
        applied_discount: { name: string } | null;
        options: { id: number; name: string; quantity: number }[];
    } | null;
};
const selectClass = 'h-10 w-full rounded-md border bg-background px-3 text-sm';
export default function CatalogShow({
    product,
    variants,
    secondVariants,
    groups,
    canManualDiscount,
    quoteUrl,
    quote,
}: Props) {
    const [manual, setManual] = useState(false);
    const [quoteVisible, setQuoteVisible] = useState(Boolean(quote));
    const form = useForm({
        variant_id: variants[0]?.id.toString() ?? '',
        second_variant_id: '',
        quantity: '1',
        options: {} as Record<string, string>,
        manual_discount: { type: 'percentage', value: '' },
    });
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title={product.name} />
            <div className="flex flex-wrap justify-between gap-4">
                <Heading
                    title={product.name}
                    description={product.description ?? ''}
                />
                <Button variant="outline" asChild>
                    <Link href="/catalogo">Voltar ao catálogo</Link>
                </Button>
            </div>
            <div className="grid items-start gap-6 lg:grid-cols-2">
                <Card>
                    <CardContent className="space-y-5 pt-6">
                        {product.photo && (
                            <img
                                src={product.photo}
                                alt={product.name}
                                className="max-h-72 w-full rounded-lg object-contain"
                            />
                        )}
                        {product.composition.length > 0 && (
                            <div>
                                <h2 className="font-medium">Composição fixa</h2>
                                <ul className="list-inside list-disc text-sm">
                                    {product.composition.map((part, index) => (
                                        <li key={index}>
                                            {part.quantity} × {part.name}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        {variants.length === 0 ? (
                            <p>
                                Este produto não possui apresentação disponível
                                no momento.
                            </p>
                        ) : (
                            <form
                                onChange={() => setQuoteVisible(false)}
                                className="space-y-5"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.transform((data) => ({
                                        ...data,
                                        manual_discount: manual
                                            ? data.manual_discount
                                            : null,
                                    }));
                                    form.post(quoteUrl, {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setQuoteVisible(true);
                                        },
                                    });
                                }}
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="variant_id">
                                        Apresentação
                                    </Label>
                                    <select
                                        className={selectClass}
                                        id="variant_id"
                                        value={form.data.variant_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'variant_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {variants.map((variant) => (
                                            <option
                                                key={variant.id}
                                                value={variant.id}
                                            >
                                                {variant.name} · preço base{' '}
                                                {variant.price}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={form.errors.variant_id}
                                    />
                                </div>
                                {product.isPizza && (
                                    <div className="space-y-2">
                                        <Label htmlFor="second_variant_id">
                                            Segundo sabor (opcional)
                                        </Label>
                                        <select
                                            id="second_variant_id"
                                            className={selectClass}
                                            value={form.data.second_variant_id}
                                            onChange={(event) =>
                                                form.setData(
                                                    'second_variant_id',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">Um sabor</option>
                                            {secondVariants.map((variant) => (
                                                <option
                                                    key={variant.id}
                                                    value={variant.id}
                                                >
                                                    {variant.name}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-muted-foreground">
                                            Escolha o mesmo tamanho da primeira
                                            apresentação. O cálculo usa o sabor
                                            mais caro.
                                        </p>
                                        <InputError
                                            message={
                                                form.errors.second_variant_id
                                            }
                                        />
                                    </div>
                                )}
                                <div className="space-y-2">
                                    <Label htmlFor="quantity">Quantidade</Label>
                                    <Input
                                        id="quantity"
                                        type="number"
                                        min="1"
                                        max="99"
                                        value={form.data.quantity}
                                        onChange={(event) =>
                                            form.setData(
                                                'quantity',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.quantity}
                                    />
                                </div>
                                {groups.map((group) => (
                                    <fieldset
                                        key={group.id}
                                        className="space-y-3 rounded-lg border p-4"
                                    >
                                        <legend className="px-2 font-medium">
                                            {group.name}
                                        </legend>
                                        <p className="text-xs text-muted-foreground">
                                            {group.hint}
                                        </p>
                                        {group.options.map((option) => (
                                            <div
                                                className="flex items-center justify-between gap-3"
                                                key={option.id}
                                            >
                                                <Label
                                                    htmlFor={
                                                        'option-' + option.id
                                                    }
                                                >
                                                    {option.name} ·{' '}
                                                    {option.price}
                                                </Label>
                                                <Input
                                                    className="w-20"
                                                    id={'option-' + option.id}
                                                    type="number"
                                                    min="0"
                                                    max={option.max}
                                                    value={
                                                        form.data.options[
                                                            option.id
                                                        ] ?? '0'
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'options',
                                                            {
                                                                ...form.data
                                                                    .options,
                                                                [option.id]:
                                                                    event.target
                                                                        .value,
                                                            },
                                                        )
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </fieldset>
                                ))}
                                {Object.entries(form.errors)
                                    .filter(
                                        ([key]) =>
                                            key === 'options' ||
                                            key.startsWith('options.'),
                                    )
                                    .map(([key, message]) => (
                                        <InputError
                                            key={key}
                                            message={message}
                                        />
                                    ))}
                                {canManualDiscount && (
                                    <fieldset className="space-y-3 rounded-lg border p-4">
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={manual}
                                                onChange={(event) =>
                                                    setManual(
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            Simular desconto manual
                                        </label>
                                        {manual && (
                                            <>
                                                <select
                                                    className={selectClass}
                                                    aria-label="Tipo de desconto manual"
                                                    value={
                                                        form.data
                                                            .manual_discount
                                                            .type
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'manual_discount',
                                                            {
                                                                ...form.data
                                                                    .manual_discount,
                                                                type: event
                                                                    .target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <option value="percentage">
                                                        Percentual (%)
                                                    </option>
                                                    <option value="fixed">
                                                        Valor fixo (R$)
                                                    </option>
                                                </select>
                                                <Input
                                                    aria-label="Valor de desconto manual"
                                                    placeholder="Ex.: 10,00"
                                                    value={
                                                        form.data
                                                            .manual_discount
                                                            .value
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'manual_discount',
                                                            {
                                                                ...form.data
                                                                    .manual_discount,
                                                                value: event
                                                                    .target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        form.errors[
                                                            'manual_discount.value'
                                                        ]
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        form.errors[
                                                            'manual_discount.type'
                                                        ]
                                                    }
                                                />
                                            </>
                                        )}
                                    </fieldset>
                                )}
                                <Button disabled={form.processing}>
                                    Calcular preço
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>
                <Card aria-live="polite">
                    <CardHeader>
                        <CardTitle>Resultado da consulta</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {quote && quoteVisible ? (
                            <>
                                <p className="font-medium">
                                    {quote.name} · {quote.variant_name}
                                </p>
                                <p>
                                    {quote.quantity} unidade(s) ·{' '}
                                    {quote.unit_label} cada
                                </p>
                                {quote.options.map((option) => (
                                    <p className="text-sm" key={option.id}>
                                        {option.quantity} × {option.name}
                                    </p>
                                ))}
                                <dl className="space-y-2">
                                    <div className="flex justify-between">
                                        <dt>Subtotal</dt>
                                        <dd>{quote.gross_label}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt>Desconto</dt>
                                        <dd>{quote.discount_label}</dd>
                                    </div>
                                    <div className="flex justify-between text-xl font-semibold">
                                        <dt>Total</dt>
                                        <dd>{quote.total_label}</dd>
                                    </div>
                                </dl>
                                {quote.applied_discount && (
                                    <p className="text-sm">
                                        Aplicado: {quote.applied_discount.name}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Prévia no momento da consulta. O preço será
                                    recalculado ao confirmar um pedido.
                                </p>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Selecione as apresentações e opções e clique em
                                calcular. Esta consulta não cria pedidos.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
CatalogShow.layout = {
    breadcrumbs: [
        { title: 'Catálogo', href: '/catalogo' },
        { title: 'Consultar preço', href: '#' },
    ],
};
