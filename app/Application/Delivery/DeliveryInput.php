<?php

namespace App\Application\Delivery;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryInput
{
    public const STATES = 'AC,AL,AP,AM,BA,CE,DF,ES,GO,MA,MT,MS,MG,PA,PB,PR,PE,PI,RJ,RN,RS,RO,RR,SC,SP,SE,TO';

    /** @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function address(array $input): array
    {
        if (is_string($input['state'] ?? null)) {
            $input['state'] = strtoupper(trim($input['state']));
        }
        $data = Validator::make(['address' => $input], ['address' => 'required|array:recipient,street,number,complement,neighborhood,city,state,cep',
            'address.recipient' => 'required|string|max:120', 'address.street' => 'required|string|max:180',
            'address.number' => 'required|string|max:30', 'address.complement' => 'nullable|string|max:180',
            'address.neighborhood' => 'required|string|max:120', 'address.city' => 'required|string|max:120',
            'address.state' => 'required|string|in:'.self::STATES,
            'address.cep' => ['required', 'string', 'regex:/^[0-9]{5}-?[0-9]{3}$/']])->validate()['address'];
        $data = array_map(fn ($value) => trim($value ?? ''), $data);
        $data['complement'] ??= '';
        $data['state'] = strtoupper($data['state']);
        $data['cep'] = str_replace('-', '', $data['cep']);

        return $data;
    }

    public static function phone(string $input): string
    {
        $digits = preg_replace('/[\s().+-]/u', '', $input);
        if (is_string($digits) && in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }
        if (! is_string($digits) || ! preg_match('/^[1-9][0-9](?:[2-8][0-9]{7}|9[0-9]{8})$/', $digits)) {
            throw ValidationException::withMessages(['phone' => 'Informe telefone brasileiro com DDD e 8 ou 9 dígitos, com +55 opcional.']);
        }

        return '+55'.$digits;
    }
}
