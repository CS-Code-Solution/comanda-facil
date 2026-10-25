<?php

namespace App\Http\Requests\Staff;

use App\Application\Staff\StaffCredentials;
use App\Enums\StaffRole;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends StaffManagementRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'max:255'],
            'username' => StaffCredentials::usernameRules(),
            'role' => ['required', Rule::enum(StaffRole::class)],
            'pin' => [...StaffCredentials::pinRules(), 'confirmed'],
        ];
    }
}
