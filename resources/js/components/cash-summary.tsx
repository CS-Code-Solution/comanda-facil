import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
export type CashQuoteData = {
    total: string;
    gross: string;
    discount: string;
    additionalDiscount: string;
    discountReason: string | null;
    lines: {
        reference: string;
        name: string;
        presentation: string;
        quantity: number;
        note: string | null;
        options: string[];
        gross: string;
        discount: string;
        total: string;
    }[];
};
export type ShiftData = {
    id: number;
    version: number;
    openedAt: string;
    closedAt: string | null;
    opening: string;
    openedBy: string;
    closedBy: string | null;
    reason: string | null;
    rows: {
        key: string;
        method: string;
        received: string;
        refunded: string;
        expected: string;
        counted: string | null;
        difference: string | null;
    }[];
};
export function CashQuote({ quote }: { quote: CashQuoteData }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Conferência da conta</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <ol className="divide-y">
                    {quote.lines.map((line, i) => (
                        <li className="space-y-1 py-3" key={i}>
                            <p className="font-semibold">
                                {line.quantity} × {line.name} ·{' '}
                                {line.presentation}
                            </p>
                            {line.options.map((option, j) => (
                                <p key={j} className="text-sm">
                                    {option}
                                </p>
                            ))}
                            {line.note && (
                                <p className="text-sm">
                                    Observação: {line.note}
                                </p>
                            )}
                            <p className="text-sm">
                                Bruto {line.gross} · Desconto {line.discount} ·
                                Final {line.total}
                            </p>
                        </li>
                    ))}
                </ol>
                <div className="space-y-2 border-t pt-3">
                    <p>Bruto: {quote.gross}</p>
                    <p>Descontos: {quote.discount}</p>
                    <p>
                        Desconto adicional no fechamento:{' '}
                        {quote.additionalDiscount}
                    </p>
                    {quote.discountReason && (
                        <p>Motivo: {quote.discountReason}</p>
                    )}
                    <p className="text-xl font-bold">
                        Total da conta: {quote.total}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
export function ShiftSummary({ shift }: { shift: ShiftData }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    Turno #{shift.id} · {shift.closedAt ? 'Fechado' : 'Aberto'}
                </CardTitle>
                <p className="text-sm">
                    Abertura: {new Date(shift.openedAt).toLocaleString('pt-BR')}
                </p>
            </CardHeader>
            <CardContent className="space-y-4">
                <p>
                    Fundo inicial em dinheiro: {shift.opening} · Abertura por{' '}
                    {shift.openedBy}
                </p>
                <div className="grid gap-3 md:grid-cols-3">
                    {shift.rows.map((row) => (
                        <div
                            className="space-y-1 rounded border p-3"
                            key={row.key}
                        >
                            <h3 className="font-semibold">{row.method}</h3>
                            <p>Recebido: {row.received}</p>
                            <p>Devolvido: {row.refunded}</p>
                            <p className="font-semibold">
                                Esperado: {row.expected}
                            </p>
                            {row.counted !== null && (
                                <>
                                    <p>Conferido: {row.counted}</p>
                                    <p>Diferença: {row.difference}</p>
                                </>
                            )}
                        </div>
                    ))}
                </div>
                {shift.closedAt && (
                    <p>
                        Fechamento:{' '}
                        {new Date(shift.closedAt).toLocaleString('pt-BR')}
                    </p>
                )}
                {shift.reason && <p>Motivo: {shift.reason}</p>}
            </CardContent>
        </Card>
    );
}
