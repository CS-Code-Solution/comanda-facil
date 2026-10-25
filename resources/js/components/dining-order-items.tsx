export type OrderLine = {
    name: string;
    presentation: string;
    quantity: number;
    total: string;
    discount: string;
    appliedDiscount: string | null;
    note: string | null;
    options: string[];
};
export default function DiningOrderItems({ items }: { items: OrderLine[] }) {
    return (
        <ol className="divide-y">
            {items.map((item, index) => (
                <li key={index} className="space-y-2 py-4">
                    <div className="flex justify-between gap-4">
                        <p className="font-medium">
                            {item.quantity} × {item.name}
                        </p>
                        <p className="font-medium whitespace-nowrap">
                            {item.total}
                        </p>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {item.presentation}
                    </p>
                    {item.options.map((option, i) => (
                        <p className="text-sm" key={i}>
                            {option}
                        </p>
                    ))}
                    {item.appliedDiscount && (
                        <p className="text-sm">
                            {item.appliedDiscount} · desconto {item.discount}
                        </p>
                    )}
                    {item.note && (
                        <p className="text-sm">Observação: {item.note}</p>
                    )}
                </li>
            ))}
        </ol>
    );
}
