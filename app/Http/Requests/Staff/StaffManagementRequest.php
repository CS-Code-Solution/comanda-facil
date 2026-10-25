<?php

namespace App\Http\Requests\Staff;

use App\Application\Staff\StaffAccessContext;
use App\Application\Staff\StaffCredentials;
use App\Enums\StaffPermission;
use Illuminate\Foundation\Http\FormRequest;

class StaffManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(StaffPermission::ManageStaff) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => StaffCredentials::normalizeUsername($this->input('username'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['current_pin' => StaffCredentials::pinRules()];
    }

    public function accessContext(): StaffAccessContext
    {
        return StaffAccessContext::web($this->user(), $this->string('current_pin')->toString());
    }
}
