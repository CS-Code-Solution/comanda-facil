<?php

namespace App\Http\Requests\Staff;

use App\Application\Staff\StaffCredentials;

class ChangeOwnPinRequest extends StaffManagementRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActiveStaff() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'pin' => [...StaffCredentials::pinRules(), 'confirmed']];
    }
}
