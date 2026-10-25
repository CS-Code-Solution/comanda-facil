<?php

namespace App\Application\Reports;

use Illuminate\Support\Facades\DB;
use LogicException;

class ShiftReportData
{
    /** One SQL statement gives the aggregates a single PostgreSQL snapshot.
     * @return array<string, mixed>
     */
    public function build(?int $shiftId): array
    {
        $row = DB::selectOne(<<<'SQL'
            WITH scope AS (SELECT ?::bigint AS shift_id),
            modes(modality) AS (VALUES ('dining_room'), ('pickup'), ('delivery')),
            methods(method) AS (VALUES ('cash'), ('pix'), ('card')),
            orders AS (
                SELECT o.id, o.modality, o.source, o.actor_id, o.actor_role, o.table_id, o.status,
                    o.total_cents, COALESCE(a.fee_cents, 0) AS fee_cents
                FROM dining_orders o
                LEFT JOIN delivery_accounts a ON a.id = o.delivery_account_id
                WHERE o.cash_shift_id IS NOT DISTINCT FROM (SELECT shift_id FROM scope)
            ),
            counts AS (
                SELECT modality, count(*) AS orders, count(*) FILTER (WHERE status='cancelled') AS cancelled,
                    count(*) FILTER (WHERE status<>'cancelled') AS active,
                    COALESCE(sum(total_cents + fee_cents) FILTER (WHERE status='cancelled'), 0) AS cancelled_cents
                FROM orders GROUP BY modality
            ),
            waiters AS (
                SELECT o.actor_id, COALESCE(u.name, 'Funcionário #' || o.actor_id::text) AS name,
                    count(DISTINCT o.table_id) AS tables, count(*) AS orders,
                    count(*) FILTER (WHERE o.status='cancelled') AS cancelled
                FROM orders o LEFT JOIN users u ON u.id=o.actor_id
                WHERE o.modality='dining_room' AND o.source='staff' AND o.actor_role='garcom'
                GROUP BY o.actor_id, u.name
            ),
            payments AS (
                SELECT p.*, CASE WHEN p.delivery_account_id IS NOT NULL THEN 'delivery'
                    WHEN p.pickup_account_id IS NOT NULL THEN 'pickup' ELSE 'dining_room' END AS modality
                FROM cash_payments p WHERE p.shift_id=(SELECT shift_id FROM scope)
            ),
            sales AS (
                SELECT modality, count(*) AS payments,
                    sum(COALESCE((snapshot->>'gross_cents')::bigint, amount_cents)) AS gross_cents,
                    sum(COALESCE((snapshot->>'discount_cents')::bigint, 0)) AS discount_cents,
                    sum(COALESCE((snapshot->>'delivery_fee_cents')::bigint, 0)) AS fee_cents,
                    sum(amount_cents) AS received_cents
                FROM payments GROUP BY modality
            ),
            refund_entries AS (
                SELECT CASE WHEN p.delivery_account_id IS NOT NULL THEN 'delivery'
                    WHEN p.pickup_account_id IS NOT NULL THEN 'pickup' ELSE 'dining_room' END AS modality,
                    r.method, r.amount_cents
                FROM cash_refunds r JOIN cash_payments p ON p.id=r.payment_id
                WHERE r.shift_id=(SELECT shift_id FROM scope)
            ),
            refunds AS (
                SELECT modality, count(*) AS refunds, sum(amount_cents) AS refunded_cents
                FROM refund_entries GROUP BY modality
            ),
            method_totals AS (
                SELECT m.method, COALESCE(p.received,0) AS received, COALESCE(r.refunded,0) AS refunded,
                    COALESCE(p.payments,0) AS payments, COALESCE(r.refunds,0) AS refunds
                FROM methods m
                LEFT JOIN (SELECT method, sum(amount_cents) AS received, count(*) AS payments FROM payments GROUP BY method) p USING(method)
                LEFT JOIN (SELECT method, sum(amount_cents) AS refunded, count(*) AS refunds FROM refund_entries GROUP BY method) r USING(method)
            ),
            cash_totals AS (
                SELECT method, received, refunded, payments, refunds,
                    received-refunded + CASE WHEN method='cash' THEN COALESCE(
                        (SELECT opening_cents FROM cash_shifts WHERE id=(SELECT shift_id FROM scope)),0) ELSE 0 END AS expected
                FROM method_totals
            ),
            summary AS (
                SELECT m.modality, COALESCE(c.orders,0) AS orders, COALESCE(c.active,0) AS active,
                    COALESCE(c.cancelled,0) AS cancelled, COALESCE(c.cancelled_cents,0) AS cancelled_cents,
                    COALESCE(s.payments,0) AS payments, COALESCE(s.gross_cents,0) AS gross_cents,
                    COALESCE(s.discount_cents,0) AS discount_cents, COALESCE(s.fee_cents,0) AS fee_cents,
                    COALESCE(s.received_cents,0) AS received_cents,
                    COALESCE(r.refunds,0) AS refunds, COALESCE(r.refunded_cents,0) AS refunded_cents,
                    COALESCE(s.received_cents,0)-COALESCE(r.refunded_cents,0) AS net_cents
                FROM modes m LEFT JOIN counts c USING(modality) LEFT JOIN sales s USING(modality) LEFT JOIN refunds r USING(modality)
            )
            SELECT jsonb_build_object(
                'modalities', (SELECT jsonb_agg(to_jsonb(s) ORDER BY modality) FROM summary s),
                'waiters', COALESCE((SELECT jsonb_agg(to_jsonb(w) ORDER BY name, actor_id) FROM waiters w), '[]'::jsonb),
                'qr_orders', (SELECT count(*) FROM orders WHERE modality='dining_room' AND source='qr'),
                'unknown_authorship', (SELECT count(*) FROM orders WHERE modality='dining_room' AND source='staff' AND actor_role IS NULL),
                'cash_totals', (SELECT jsonb_object_agg(method, to_jsonb(c)-'method') FROM cash_totals c)
            ) AS data
            SQL, [$shiftId]);
        if (! $row) {
            throw new LogicException('Consulta de resumo sem resultado.');
        }
        $data = json_decode($row->data, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new LogicException('Resumo de turno inválido.');
        }

        return [...$data, 'schema_version' => 1, 'generated_at' => now()->toIso8601String()];
    }
}
