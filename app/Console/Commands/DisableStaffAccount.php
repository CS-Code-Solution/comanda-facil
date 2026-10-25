<?php

namespace App\Console\Commands;

use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Application\Staff\StaffCredentials;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class DisableStaffAccount extends Command
{
    protected $signature = 'staff:disable {username} {--force}';

    protected $description = 'Desativa uma conta e revoga suas sessões';

    public function handle(ManageStaffAccount $accounts): int
    {
        $user = User::query()->where('username', StaffCredentials::normalizeUsername((string) $this->argument('username')))->first();

        if (! $user) {
            $this->error('Conta não encontrada.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Desativar a conta '.$user->username.'?')) {
            return self::FAILURE;
        }

        try {
            $accounts->disable($user, StaffAccessContext::console());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info('Conta desativada.');

        return self::SUCCESS;
    }
}
