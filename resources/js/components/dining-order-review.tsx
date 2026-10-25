import { Form, Head, Link } from '@inertiajs/react';
import DiningOrderItems, {
    type OrderLine,
} from '@/components/dining-order-items';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
export type ReviewProps = {
    tableNumber: number;
    items: OrderLine[];
    total: string;
    expiresAt: string;
    expired: boolean;
    confirmUrl: string;
    backUrl: string;
};
export default function DiningOrderReview({
    tableNumber,
    items,
    total,
    expiresAt,
    expired,
    confirmUrl,
    backUrl,
}: ReviewProps) {
    return (
        <main className="mx-auto max-w-2xl space-y-6 p-4 md:p-6">
            <Head title="Conferir pedido" />
            <h1 className="text-2xl font-semibold">
                Confira o pedido · Mesa {tableNumber}
            </h1>
            <Card>
                <CardHeader>
                    <CardTitle>Itens selecionados</CardTitle>
                </CardHeader>
                <CardContent>
                    <DiningOrderItems items={items} />
                    <div className="flex justify-between border-t pt-4 text-xl font-semibold">
                        <span>Total</span>
                        <span>{total}</span>
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">
                        Prévia válida até{' '}
                        {new Date(expiresAt).toLocaleTimeString('pt-BR')}. O
                        preço será conferido novamente ao enviar.
                    </p>
                </CardContent>
            </Card>
            {expired && (
                <p role="alert">
                    A prévia expirou. Volte ao cardápio e faça uma nova seleção.
                </p>
            )}
            <Form action={confirmUrl} method="post" className="space-y-3">
                {({ processing, errors }) => (
                    <>
                        {Object.entries(errors).map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                        <Button disabled={processing || expired}>
                            {processing
                                ? 'Enviando…'
                                : 'Confirmar e enviar pedido'}
                        </Button>
                    </>
                )}
            </Form>
            <Button asChild variant="outline">
                <Link href={backUrl}>Voltar ao cardápio</Link>
            </Button>
        </main>
    );
}
