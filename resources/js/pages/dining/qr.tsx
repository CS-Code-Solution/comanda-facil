import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
export default function DiningQr({
    number,
    url,
    imageUrl,
    backUrl,
}: {
    number: number;
    url: string;
    imageUrl: string;
    backUrl: string;
}) {
    const [qrReady, setQrReady] = useState(false);
    const [qrError, setQrError] = useState(false);
    return (
        <main className="mx-auto max-w-lg space-y-6 p-6 text-center">
            <Head title={'QR · Mesa ' + number} />
            <h1 className="text-4xl font-bold">Mesa {number}</h1>
            <p>Leia o QR para abrir o cardápio e fazer seu pedido.</p>
            <img
                src={imageUrl}
                alt={'QR Code do cardápio da mesa ' + number}
                width={400}
                height={400}
                className="mx-auto w-full max-w-sm bg-white"
                onLoad={() => setQrReady(true)}
                onError={() => setQrError(true)}
            />
            <p className="text-xs break-all text-muted-foreground">{url}</p>
            <div className="flex justify-center gap-3 print:hidden">
                <Button disabled={!qrReady} onClick={() => window.print()}>
                    Imprimir QR
                </Button>
                <Button asChild variant="outline">
                    <a href={imageUrl} download={'mesa-' + number + '.svg'}>
                        Baixar SVG
                    </a>
                </Button>
                <Button asChild variant="outline">
                    <Link href={backUrl}>Voltar</Link>
                </Button>
            </div>
            {qrError && (
                <p role="alert">
                    Não foi possível carregar o QR. Reabra esta página antes de
                    imprimir.
                </p>
            )}
        </main>
    );
}
