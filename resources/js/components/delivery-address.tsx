import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type DeliveryAddress = {
    recipient: string;
    cep: string;
    street: string;
    number: string;
    complement: string;
    neighborhood: string;
    city: string;
    state: string;
};

export const emptyAddress: DeliveryAddress = {
    recipient: '',
    cep: '',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
};

export function DeliveryAddressFields({
    value,
    change,
}: {
    value: DeliveryAddress;
    change: (address: DeliveryAddress) => void;
}) {
    const fields: {
        key: keyof DeliveryAddress;
        label: string;
        max: number;
        autoComplete?: string;
    }[] = [
        {
            key: 'recipient',
            label: 'Nome de quem recebe',
            max: 120,
            autoComplete: 'name',
        },
        { key: 'cep', label: 'CEP', max: 9, autoComplete: 'postal-code' },
        {
            key: 'street',
            label: 'Rua / avenida',
            max: 180,
            autoComplete: 'address-line1',
        },
        { key: 'number', label: 'Número (ou s/n)', max: 30 },
        {
            key: 'complement',
            label: 'Complemento (opcional)',
            max: 180,
            autoComplete: 'address-line2',
        },
        { key: 'neighborhood', label: 'Bairro', max: 120 },
        {
            key: 'city',
            label: 'Cidade',
            max: 120,
            autoComplete: 'address-level2',
        },
        { key: 'state', label: 'UF', max: 2, autoComplete: 'address-level1' },
    ];
    return (
        <fieldset className="grid gap-3 rounded border p-4 sm:grid-cols-2">
            <legend className="px-2 font-semibold">Endereço de entrega</legend>
            {fields.map((field) => (
                <div className="space-y-2" key={field.key}>
                    <Label htmlFor={'address-' + field.key}>
                        {field.label}
                    </Label>
                    <Input
                        id={'address-' + field.key}
                        maxLength={field.max}
                        autoComplete={field.autoComplete}
                        required={field.key !== 'complement'}
                        value={value[field.key]}
                        onChange={(event) =>
                            change({
                                ...value,
                                [field.key]: event.target.value,
                            })
                        }
                    />
                </div>
            ))}
            <p className="text-sm text-muted-foreground sm:col-span-2">
                A cobertura, o pedido mínimo e a taxa serão conferidos antes da
                confirmação. Pagamento ao entregador no recebimento.
            </p>
        </fieldset>
    );
}

export function AddressSummary({ address }: { address: DeliveryAddress }) {
    return (
        <div className="space-y-1 text-sm">
            <p>{address.recipient}</p>
            <p>
                {address.street}, {address.number}
                {address.complement ? ' · ' + address.complement : ''}
            </p>
            <p>
                {address.neighborhood} · {address.city}/{address.state} · CEP{' '}
                {address.cep}
            </p>
        </div>
    );
}
