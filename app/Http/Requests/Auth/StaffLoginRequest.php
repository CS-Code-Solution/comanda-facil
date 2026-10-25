<?php

namespace App\Http\Requests\Auth;

use App\Application\Staff\StaffCredentials;
use Illuminate\Foundation\Http\FormRequest;

class StaffLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => StaffCredentials::normalizeUsername($this->input('username'))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'username' => StaffCredentials::usernameRules(),
            'pin' => StaffCredentials::pinRules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'username.required' => 'Informe seu usuário.',
            'username.regex' => 'Informe um usuário válido.',
            'pin.required' => 'Informe seu PIN.',
            'pin.regex' => 'O PIN deve conter seis dígitos.',
        ];
    }
}
