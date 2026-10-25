import { Head, Link, useForm } from '@inertiajs/react';
import { CashQuote, type CashQuoteData } from '@/components/cash-summary';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
export default function Review({
    quote,
    locationLabel,
    expiresAt,
    expired,
    zero,
    receivedDefault,
    confirmUrl,
    backUrl,
}: {
    quote: CashQuoteData;
    accountId: number;
    table: number | null;
    locationLabel: string;
    expiresAt: string;
    expired: boolean;
    zero: boolean;
    receivedDefault: string;
    confirmUrl: string;
    backUrl: string;
}) {
    const form = useForm({
        method: 'cash',
        received: receivedDefault,
        external_reference: '',
    });
    return (
        <div className="mx-auto max-w-3xl space-y-5 p-4 md:p-6">
            <Head title="Confirmar pagamento" />
            <h1 className="text-2xl font-semibold">
                Confirmar pagamento · {locationLabel}
            </h1>
            <CashQuote quote={quote} />
            <p className="text-sm">
                Conferência válida até{' '}
                {new Date(expiresAt).toLocaleTimeString('pt-BR')}.
            </p>
            {expired && (
                <p role="alert">
                    A prévia expirou. Volte à conta e confira novamente.
                </p>
            )}
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        received: data.method === 'cash' ? data.received : null,
                    }));
                    form.post(confirmUrl);
                }}
            >
                <Label htmlFor="payment-method">Forma de pagamento</Label>
                <select
                    id="payment-method"
                    className="h-10 w-full rounded border bg-background px-3"
                    value={form.data.method}
                    onChange={(event) =>
                        form.setData('method', event.target.value)
                    }
                >
                    <option value="cash">
                        {zero ? 'Quitação sem valor' : 'Dinheiro'}
                    </option>
                    {!zero && (
                        <>
                            <option value="pix">Pix (registro manual)</option>
                            <option value="card">
                                Cartão (registro manual)
                            </option>
                        </>
                    )}
                </select>
                {form.data.method === 'cash' && (
                    <>
                        <Label htmlFor="received">Dinheiro recebido (R$)</Label>
                        <Input
                            id="received"
                            required
                            inputMode="decimal"
                            value={form.data.received}
                            onChange={(event) =>
                                form.setData('received', event.target.value)
                            }
                        />
                        <p className="text-sm">
                            Após registrar, confira o troco no registro de
                            pagamento.
                        </p>
                    </>
                )}
                <Label htmlFor="payment-reference">
                    Referência interna opcional (sem dados bancários ou de
                    cartão)
                </Label>
                <Input
                    id="payment-reference"
                    maxLength={100}
                    value={form.data.external_reference}
                    onChange={(event) =>
                        form.setData('external_reference', event.target.value)
                    }
                />
                <p className="text-sm">
                    Confirme o recebimento na operação do restaurante antes de
                    registrar. O sistema não processa Pix ou cartão.
                </p>
                {Object.entries(form.errors).map(([key, message]) => (
                    <InputError key={key} message={message} />
                ))}
                <div className="flex flex-wrap gap-3">
                    <Button disabled={form.processing || expired}>
                        Registrar pagamento integral
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={backUrl}>Voltar à conta</Link>
                    </Button>
                </div>
            </form>
        </div>
    );
}
