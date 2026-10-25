export type SecondFlavor = { id: number; productId: number; name: string };
export type MenuVariant = {
    id: number;
    name: string;
    price: string;
    secondFlavors: SecondFlavor[];
};

// Remap the displayed selection using the choices supplied by Laravel.
export function secondFlavorAfterSizeChange(
    variants: MenuVariant[],
    currentId: string,
    nextId: string,
    secondId: string,
): string {
    const selected = variants
        .find((variant) => String(variant.id) === currentId)
        ?.secondFlavors.find((flavor) => String(flavor.id) === secondId);
    return (
        variants
            .find((variant) => String(variant.id) === nextId)
            ?.secondFlavors.find(
                (flavor) => flavor.productId === selected?.productId,
            )
            ?.id.toString() ?? ''
    );
}
