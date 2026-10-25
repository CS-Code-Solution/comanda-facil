<?php

namespace App\Console\Commands;

use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Enums\StaffRole;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateStaffAccount extends Command
{
    protected $signature = 'staff:create {username} {--name=} {--role=}';

    protected $description = 'Cria uma conta individual; solicita o PIN sem exibi-lo';

    public function handle(ManageStaffAccount $accounts): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Use um terminal interativo para informar o PIN de forma oculta.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Nome'));
        $roleValue = $this->option('role') ?: $this->choice('Papel', array_column(StaffRole::cases(), 'value'));
        $role = is_string($roleValue) ? StaffRole::tryFrom($roleValue) : null;

        if (! $role) {
            $this->error('Papel inválido.');

            return self::FAILURE;
        }

        $pin = (string) $this->secret('PIN (6 dígitos)', false);
        $confirmation = (string) $this->secret('Confirme o PIN', false);

        try {
            $user = $accounts->create($name, (string) $this->argument('username'), $role, $pin, $confirmation, StaffAccessContext::console());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info('Conta criada: '.$user->username.' ('.$user->role.').');

        return self::SUCCESS;
    }
}
