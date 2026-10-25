import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    PaperChoice,
    ThermalStyle,
    type Paper,
} from '@/components/thermal-paper';
import { CashQuote } from '@/components/cash-summary';
import { Button } from '@/components/ui/button';
import type { PaymentData } from './account';
export default function Receipt({
    payment,
    locationLabel,
    backUrl,
}: {
    payment: PaymentData;
    table: number | null;
    locationLabel: string;
    accountId: number;
    backUrl: string;
}) {
    const [paper, setPaper] = useState<Paper>('80');
    return (
        <main className="thermal-ticket mx-auto max-w-3xl space-y-4 p-4 text-foreground print:max-w-none print:p-0 print:text-black">
            <Head title="Registro de pagamento" />
            <ThermalStyle paper={paper} />
            <div className="cash-controls flex flex-wrap gap-3">
                <PaperChoice paper={paper} setPaper={setPaper} />
                <Button onClick={() => window.print()}>
                    Imprimir registro
                </Button>
                <Button asChild variant="outline">
                    <Link href={backUrl}>Voltar à conta</Link>
                </Button>
            </div>
            <h1 className="text-2xl font-semibold">
                Comanda Fácil · Registro manual de pagamento
            </h1>
            <p>
                {locationLabel} · Turno #{payment.shiftId}
            </p>
            <p className="text-sm break-all">Referência: {payment.reference}</p>
            <p>{new Date(payment.createdAt).toLocaleString('pt-BR')}</p>
            <CashQuote quote={payment.quote} />
            <p className="font-bold">
                {payment.method} · {payment.amount}
            </p>
            {payment.received && (
                <>
                    <p>Recebido: {payment.received}</p>
                    <p className="font-semibold">Troco: {payment.change}</p>
                </>
            )}
            <p className="font-bold">NÃO FISCAL</p>
            <p className="text-sm">
                Registro original. Devoluções posteriores constam separadamente
                no histórico da conta. Não substitui confirmação bancária ou
                comprovante da maquininha.
            </p>
        </main>
    );
}
