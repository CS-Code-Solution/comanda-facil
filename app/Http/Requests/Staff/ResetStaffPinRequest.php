<?php

namespace App\Http\Requests\Staff;

use App\Application\Staff\StaffCredentials;

class ResetStaffPinRequest extends StaffManagementRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'pin' => [...StaffCredentials::pinRules(), 'confirmed']];
    }
}
