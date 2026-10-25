import DiningOrderReview, {
    type ReviewProps,
} from '@/components/dining-order-review';
export default function Review(props: ReviewProps & { reason: string }) {
    return (
        <>
            <p className="mx-auto max-w-2xl px-4 pt-6">
                Correção de pedido · Motivo: {props.reason}
            </p>
            <DiningOrderReview {...props} />
        </>
    );
}
