<?php

namespace App\Application\Operations;

use Illuminate\Support\Str;

class OperationCorrelation
{
    private ?string $value = null;

    public function id(): string
    {
        return $this->value ??= (string) Str::uuid();
    }

    public function clear(): void
    {
        $this->value = null;
    }
}
