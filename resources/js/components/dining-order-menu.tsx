import { Form, Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import {
    DeliveryAddressFields,
    emptyAddress,
    type DeliveryAddress,
} from '@/components/delivery-address';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    secondFlavorAfterSizeChange,
    type MenuVariant,
} from '@/lib/pizza-flavors';

type ItemInput = {
    variant_id: string;
    second_variant_id: string;
    quantity: string;
    options: Record<string, string>;
    note: string;
    manual_discount?: { type: string; value: string } | null;
};
type Product = {
    id: number;
    name: string;
    category: string;
    description: string | null;
    photo: string | null;
    composition: { name: string; quantity: number }[];
    variants: MenuVariant[];
    groups: {
        id: number;
        name: string;
        hint: string;
        options: { id: number; name: string; price: string; max: number }[];
    }[];
};
export type MenuProps = {
    table: { number: number; label: string | null; cycle: number } | null;
    heading?: string;
    backUrl?: string;
    requiresPhone?: boolean;
    requiresAddress?: boolean;
    products: Product[];
    receiving: boolean;
    previewUrl: string;
    callUrl: string | null;
    staff: boolean;
    canManualDiscount?: boolean;
    initialItems?: { input: ItemInput; label: string }[];
    changeVersion?: number;
};
const selectClass = 'h-10 w-full rounded-md border bg-background px-3 text-sm';

function ProductChoice({
    product,
    disabled,
    canManualDiscount,
    add,
}: {
    product: Product;
    disabled: boolean;
    canManualDiscount: boolean;
    add: (input: ItemInput, label: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState<ItemInput>({
        variant_id: product.variants[0]?.id.toString() ?? '',
        second_variant_id: '',
        quantity: '1',
        options: {},
        note: '',
    });
    const [manual, setManual] = useState(false);
    const [discount, setDiscount] = useState({ type: 'percentage', value: '' });
    const secondFlavors =
        product.variants.find(
            (variant) => String(variant.id) === input.variant_id,
        )?.secondFlavors ?? [];
    const prefix = 'product-' + product.id;
    return (
        <Card>
            {product.photo && (
                <img
                    src={product.photo}
                    alt={product.name}
                    loading="lazy"
                    className="h-40 w-full rounded-t-lg object-cover"
                />
            )}
            <CardHeader>
                <p className="text-xs text-muted-foreground">
                    {product.category}
                </p>
                <CardTitle>{product.name}</CardTitle>
                <p className="text-sm text-muted-foreground">
                    {product.description}
                </p>
            </CardHeader>
            <CardContent className="space-y-4">
                {product.composition.length > 0 && (
                    <ul className="text-sm">
                        {product.composition.map((part, index) => (
                            <li key={index}>
                                {part.quantity} × {part.name}
                            </li>
                        ))}
                    </ul>
                )}
                {product.variants.length === 0 ? (
                    <p className="text-sm">Indisponível no momento</p>
                ) : (
                    <>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={disabled}
                            aria-expanded={open}
                            onClick={() => setOpen(!open)}
                        >
                            {open ? 'Recolher opções' : 'Escolher apresentação'}
                        </Button>
                        {open && (
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    add(
                                        {
                                            ...input,
                                            manual_discount:
                                                canManualDiscount && manual
                                                    ? discount
                                                    : null,
                                        },
                                        product.name,
                                    );
                                    setOpen(false);
                                }}
                            >
                                <div className="space-y-2">
                                    <Label htmlFor={prefix + '-variant'}>
                                        Apresentação
                                    </Label>
                                    <select
                                        id={prefix + '-variant'}
                                        className={selectClass}
                                        value={input.variant_id}
                                        onChange={(event) =>
                                            setInput({
                                                ...input,
                                                variant_id: event.target.value,
                                                second_variant_id:
                                                    secondFlavorAfterSizeChange(
                                                        product.variants,
                                                        input.variant_id,
                                                        event.target.value,
                                                        input.second_variant_id,
                                                    ),
                                            })
                                        }
                                    >
                                        {product.variants.map((variant) => (
                                            <option
                                                key={variant.id}
                                                value={variant.id}
                                            >
                                                {variant.name} · base{' '}
                                                {variant.price}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                {product.variants.some(
                                    (variant) =>
                                        variant.secondFlavors.length > 0,
                                ) && (
                                    <div className="space-y-2">
                                        <Label htmlFor={prefix + '-second'}>
                                            Segundo sabor (opcional)
                                        </Label>
                                        <select
                                            className={selectClass}
                                            id={prefix + '-second'}
                                            value={input.second_variant_id}
                                            onChange={(event) =>
                                                setInput({
                                                    ...input,
                                                    second_variant_id:
                                                        event.target.value,
                                                })
                                            }
                                        >
                                            <option value="">Um sabor</option>
                                            {secondFlavors.map((variant) => (
                                                <option
                                                    key={variant.id}
                                                    value={variant.id}
                                                >
                                                    {variant.name}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-muted-foreground">
                                            O tamanho acompanha a primeira
                                            escolha. Dois sabores usam o preço
                                            do mais caro.
                                        </p>
                                    </div>
                                )}
                                <div className="space-y-2">
                                    <Label htmlFor={prefix + '-quantity'}>
                                        Quantidade
                                    </Label>
                                    <Input
                                        id={prefix + '-quantity'}
                                        type="number"
                                        min="1"
                                        max="99"
                                        value={input.quantity}
                                        onChange={(event) =>
                                            setInput({
                                                ...input,
                                                quantity: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                                {product.groups.map((group) => (
                                    <fieldset
                                        className="space-y-3 rounded-md border p-3"
                                        key={group.id}
                                    >
                                        <legend className="px-2 text-sm font-medium">
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
                                                        prefix +
                                                        '-option-' +
                                                        option.id
                                                    }
                                                >
                                                    {option.name} ·{' '}
                                                    {option.price}
                                                </Label>
                                                <Input
                                                    className="w-20"
                                                    id={
                                                        prefix +
                                                        '-option-' +
                                                        option.id
                                                    }
                                                    type="number"
                                                    min="0"
                                                    max={option.max}
                                                    value={
                                                        input.options[
                                                            option.id
                                                        ] ?? '0'
                                                    }
                                                    onChange={(event) =>
                                                        setInput({
                                                            ...input,
                                                            options: {
                                                                ...input.options,
                                                                [option.id]:
                                                                    event.target
                                                                        .value,
                                                            },
                                                        })
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </fieldset>
                                ))}
                                <div className="space-y-2">
                                    <Label htmlFor={prefix + '-note'}>
                                        Observação para a equipe
                                    </Label>
                                    <textarea
                                        className="min-h-20 w-full rounded-md border bg-background p-3 text-sm"
                                        id={prefix + '-note'}
                                        maxLength={500}
                                        value={input.note}
                                        onChange={(event) =>
                                            setInput({
                                                ...input,
                                                note: event.target.value,
                                            })
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Alterações de composição dependem das
                                        opções disponíveis e da equipe.
                                    </p>
                                </div>
                                {canManualDiscount && (
                                    <div className="space-y-3">
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
                                            Desconto manual de gestor
                                        </label>
                                        {manual && (
                                            <>
                                                <select
                                                    aria-label="Tipo de desconto"
                                                    className={selectClass}
                                                    value={discount.type}
                                                    onChange={(event) =>
                                                        setDiscount({
                                                            ...discount,
                                                            type: event.target
                                                                .value,
                                                        })
                                                    }
                                                >
                                                    <option value="percentage">
                                                        Percentual (%)
                                                    </option>
                                                    <option value="fixed">
                                                        Valor por unidade (R$)
                                                    </option>
                                                </select>
                                                <Input
                                                    aria-label="Valor do desconto"
                                                    placeholder="Ex.: 10,00"
                                                    value={discount.value}
                                                    onChange={(event) =>
                                                        setDiscount({
                                                            ...discount,
                                                            value: event.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </>
                                        )}
                                    </div>
                                )}
                                <Button disabled={disabled}>
                                    Adicionar à seleção
                                </Button>
                            </form>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}

export default function DiningOrderMenu({
    table,
    products,
    receiving,
    previewUrl,
    callUrl,
    staff,
    heading,
    backUrl,
    requiresPhone = false,
    requiresAddress = false,
    canManualDiscount = false,
    initialItems = [],
    changeVersion,
}: MenuProps) {
    const [cart, setCart] = useState<
        { key: number; input: ItemInput; label: string }[]
    >(initialItems.map((item, key) => ({ ...item, key })));
    const [search, setSearch] = useState('');
    const nextKey = useRef(initialItems.length);
    const form = useForm<{
        cycle: number | null;
        phone: string;
        address: DeliveryAddress;
        items: ItemInput[];
        version: number | null;
        reason: string;
    }>({
        cycle: table?.cycle ?? null,
        phone: '',
        address: emptyAddress,
        items: [],
        version: changeVersion ?? null,
        reason: '',
    });
    return (
        <main className="mx-auto max-w-6xl space-y-6 p-4 md:p-6">
            <Head title={heading ?? 'Mesa ' + table?.number} />
            <header className="flex flex-wrap justify-between gap-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        Comanda Fácil
                    </p>
                    <h1 className="text-3xl font-semibold">
                        {heading ?? 'Mesa ' + table?.number}
                    </h1>
                    {table?.label && <p>{table.label}</p>}
                    <p className="mt-2 text-sm">
                        Escolha os itens e confira o total antes de confirmar.
                    </p>
                </div>
                {staff ? (
                    <Button variant="outline" asChild>
                        <Link href={backUrl ?? '/salao'}>
                            Voltar ao atendimento
                        </Link>
                    </Button>
                ) : (
                    callUrl && (
                        <Form
                            action={callUrl}
                            method="post"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Button
                                        variant="outline"
                                        disabled={processing || !receiving}
                                    >
                                        Chamar garçom
                                    </Button>
                                    <InputError
                                        message={errors.call ?? errors.basket}
                                    />
                                </>
                            )}
                        </Form>
                    )
                )}
            </header>
            {!receiving && (
                <p
                    role="status"
                    className="rounded-md border border-amber-400 bg-amber-50 p-4 text-amber-950"
                >
                    O recebimento de pedidos está pausado. Procure a equipe.
                </p>
            )}
            <Card className="border-primary/30">
                <CardHeader>
                    <CardTitle>Sua seleção</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {cart.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Adicione os produtos do cardápio abaixo.
                        </p>
                    ) : (
                        <ol className="divide-y">
                            {cart.map((item, index) => (
                                <li
                                    key={item.key}
                                    className="flex items-start justify-between gap-3 py-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {index + 1}. {item.input.quantity} ×{' '}
                                            {item.label}
                                        </p>
                                        {item.input.note && (
                                            <p className="text-sm text-muted-foreground">
                                                {item.input.note}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            setCart(
                                                cart.filter(
                                                    (line) =>
                                                        line.key !== item.key,
                                                ),
                                            );
                                            form.clearErrors();
                                        }}
                                    >
                                        Remover
                                    </Button>
                                </li>
                            ))}
                        </ol>
                    )}
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.transform(({ address, ...data }) => ({
                                ...data,
                                ...(requiresAddress ? { address } : {}),
                                items: cart.map((item) => item.input),
                            }));
                            form.post(previewUrl);
                        }}
                        className="space-y-3"
                    >
                        {requiresPhone && (
                            <div className="space-y-2">
                                <Label htmlFor="pickup-phone">
                                    Telefone com DDD
                                </Label>
                                <Input
                                    id="pickup-phone"
                                    type="tel"
                                    autoComplete="tel"
                                    maxLength={40}
                                    required
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    {requiresAddress
                                        ? 'Contato para a entrega. Pagamento ao entregador no recebimento.'
                                        : 'Usado pela equipe para identificar a retirada. Pagamento no caixa antes de retirar.'}
                                </p>
                            </div>
                        )}
                        {requiresAddress && (
                            <DeliveryAddressFields
                                value={form.data.address}
                                change={(address) =>
                                    form.setData('address', address)
                                }
                            />
                        )}
                        {changeVersion !== undefined && (
                            <div className="space-y-2">
                                <Label htmlFor="change-reason">
                                    Motivo obrigatório da correção
                                </Label>
                                <Input
                                    id="change-reason"
                                    minLength={3}
                                    maxLength={500}
                                    required
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        )}
                        {Object.entries(form.errors).map(([key, message]) => (
                            <InputError
                                key={key}
                                message={
                                    key.startsWith('items.')
                                        ? 'Item ' +
                                          (Number(key.split('.')[1]) + 1) +
                                          ': ' +
                                          message
                                        : message
                                }
                            />
                        ))}
                        <Button
                            disabled={
                                form.processing ||
                                cart.length === 0 ||
                                !receiving
                            }
                        >
                            Conferir pedido e total
                        </Button>
                        <p className="text-xs text-muted-foreground">
                            Os preços e as promoções serão conferidos na próxima
                            tela. O pedido só é enviado após sua confirmação.
                        </p>
                    </form>
                </CardContent>
            </Card>
            <Input
                aria-label="Buscar no cardápio"
                placeholder="Buscar produto ou categoria"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
            />
            <div className="grid items-start gap-4 md:grid-cols-2 lg:grid-cols-3">
                {products
                    .filter((product) =>
                        (product.name + ' ' + product.category)
                            .toLocaleLowerCase('pt-BR')
                            .includes(search.toLocaleLowerCase('pt-BR')),
                    )
                    .map((product) => (
                        <ProductChoice
                            key={product.id}
                            product={product}
                            disabled={!receiving || form.processing}
                            canManualDiscount={canManualDiscount}
                            add={(input, label) => {
                                setCart([
                                    ...cart,
                                    { key: ++nextKey.current, input, label },
                                ]);
                                form.clearErrors();
                            }}
                        />
                    ))}
            </div>
            {products.length === 0 && (
                <p>Nenhum produto publicado no momento. Procure a equipe.</p>
            )}
        </main>
    );
}
