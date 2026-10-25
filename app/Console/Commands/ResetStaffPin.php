<?php

namespace App\Console\Commands;

use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Application\Staff\StaffCredentials;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ResetStaffPin extends Command
{
    protected $signature = 'staff:reset-pin {username}';

    protected $description = 'Redefine o PIN e revoga sessões anteriores da conta';

    public function handle(ManageStaffAccount $accounts): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Use um terminal interativo para informar o PIN de forma oculta.');

            return self::FAILURE;
        }

        $user = User::query()->where('username', StaffCredentials::normalizeUsername((string) $this->argument('username')))->first();

        if (! $user) {
            $this->error('Conta não encontrada.');

            return self::FAILURE;
        }

        $pin = (string) $this->secret('Novo PIN (6 dígitos)', false);
        $confirmation = (string) $this->secret('Confirme o PIN', false);

        try {
            $accounts->resetPin($user, $pin, $confirmation, StaffAccessContext::console());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info('PIN redefinido. Sessões anteriores revogadas.');

        return self::SUCCESS;
    }
}
