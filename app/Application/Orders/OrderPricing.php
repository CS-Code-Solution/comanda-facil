<?php

namespace App\Application\Orders;

use App\Application\Catalog\CatalogLock;
use App\Application\Catalog\QuoteCatalogItem;
use App\Application\Dining\DiningContext;
use App\Application\Pickup\PickupContext;
use Illuminate\Validation\ValidationException;

class OrderPricing
{
    public function __construct(private QuoteCatalogItem $pricing) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function quoteItems(array $items, DiningContext|PickupContext $context): array
    {
        CatalogLock::shared();
        $snapshot = [];
        foreach ($items as $position => $input) {
            try {
                $snapshot[] = [...$this->pricing->execute($input, $context->catalog()), 'note' => $input['note'] ?? null];
            } catch (ValidationException $error) {
                $messages = [];
                foreach ($error->errors() as $key => $value) {
                    $messages['items.'.$position.'.'.$key] = $value;
                }
                throw ValidationException::withMessages($messages);
            }
        }

        return $snapshot;
    }

    /** @param list<array<string, mixed>> $snapshot */
    public function canonical(array $snapshot): string
    {
        foreach ($snapshot as &$item) {
            unset($item['revision'], $item['quoted_at']);
        }
        unset($item);

        return json_encode($this->sortKeys($snapshot), JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function sortKeys(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->sortKeys($value);
            }
        }

        return $data;
    }
}
