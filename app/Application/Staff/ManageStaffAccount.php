<?php

namespace App\Application\Staff;

use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageStaffAccount
{
    private const MUTATION_LOCK = 2026102401;

    public function create(string $name, string $username, StaffRole $role, #[\SensitiveParameter] string $pin, #[\SensitiveParameter] string $confirmation, StaffAccessContext $context): User
    {
        return $this->mutate(function () use ($name, $username, $role, $pin, $confirmation, $context): User {
            $this->authorize($context);
            $username = StaffCredentials::normalizeUsername($username);
            $this->validateIdentity($name, $username);
            $this->validatePin($pin, $confirmation);

            $user = new User;
            $user->forceFill([
                'name' => $name,
                'username' => $username,
                'password' => Str::random(64),
                'pin_hash' => $pin,
                'role' => $role->value,
                'is_active' => true,
            ])->save();
            $user->refresh();
            $this->audit($user, 'created', $context, null);

            return $user;
        });
    }

    public function update(User $user, string $name, string $username, StaffRole $role, StaffAccessContext $context): User
    {
        return $this->mutate(function () use ($user, $name, $username, $role, $context): User {
            $this->authorize($context);
            $account = User::query()->findOrFail($user->id);
            $username = StaffCredentials::normalizeUsername($username);
            $this->validateIdentity($name, $username, $account);

            if ($context->actorId === $account->id && $role->value !== $account->role) {
                throw ValidationException::withMessages(['role' => 'Outro gestor deve alterar o seu papel.']);
            }

            $this->protectLastManager($account, $role, $account->is_active);
            $before = $this->snapshot($account);
            $roleChanged = $account->role !== $role->value;
            $credentialsChanged = $roleChanged || $account->username !== $username;
            $account->forceFill([
                'name' => $name,
                'username' => $username,
                'role' => $role->value,
                'auth_version' => $account->auth_version + ($credentialsChanged ? 1 : 0),
            ])->save();
            $this->audit($account, $roleChanged ? 'role_changed' : 'updated', $context, $before);

            return $account;
        });
    }

    public function resetPin(User $user, #[\SensitiveParameter] string $pin, #[\SensitiveParameter] string $confirmation, StaffAccessContext $context): User
    {
        return $this->mutate(function () use ($user, $pin, $confirmation, $context): User {
            $this->authorize($context);
            $this->validatePin($pin, $confirmation);
            $account = User::query()->findOrFail($user->id);
            $before = $this->snapshot($account);
            $this->replacePin($account, $pin);
            $this->audit($account, 'pin_reset', $context, $before);

            return $account;
        });
    }

    public function changeOwnPin(#[\SensitiveParameter] string $pin, #[\SensitiveParameter] string $confirmation, StaffAccessContext $context): User
    {
        return $this->mutate(function () use ($pin, $confirmation, $context): User {
            if ($context->actorId === null) {
                throw new AuthorizationException;
            }

            $account = $this->authorize($context, false);

            if ($account === null) {
                throw new AuthorizationException;
            }

            $this->validatePin($pin, $confirmation);
            $before = $this->snapshot($account);
            $this->replacePin($account, $pin);
            $this->audit($account, 'pin_changed', $context, $before);

            return $account;
        });
    }

    public function disable(User $user, StaffAccessContext $context): User
    {
        return $this->setActive($user, false, $context);
    }

    public function setActive(User $user, bool $active, StaffAccessContext $context): User
    {
        return $this->mutate(function () use ($user, $active, $context): User {
            $this->authorize($context);
            $account = User::query()->findOrFail($user->id);

            if (! $active && $context->actorId === $account->id) {
                throw ValidationException::withMessages(['account' => 'Outro gestor deve desativar sua conta.']);
            }

            if ($active && (! $account->username || ! $account->pin_hash || ! StaffRole::tryFrom($account->role ?? ''))) {
                throw ValidationException::withMessages(['account' => 'Defina usuário, papel e PIN antes de ativar a conta.']);
            }

            $this->protectLastManager($account, StaffRole::tryFrom($account->role ?? ''), $active);

            if ($account->is_active === $active) {
                return $account;
            }

            $before = $this->snapshot($account);
            $account->forceFill([
                'is_active' => $active,
                'auth_version' => $account->auth_version + 1,
                'remember_token' => null,
            ])->save();
            $this->audit($account, $active ? 'activated' : 'disabled', $context, $before);

            return $account;
        });
    }

    public function revokeSessions(User $user, StaffAccessContext $context): User
    {
        return $this->mutate(function () use ($user, $context): User {
            $this->authorize($context);
            $account = User::query()->findOrFail($user->id);
            $before = $this->snapshot($account);
            $account->forceFill([
                'auth_version' => $account->auth_version + 1,
                'remember_token' => null,
            ])->save();
            $this->audit($account, 'sessions_revoked', $context, $before);

            return $account;
        });
    }

    private function authorize(StaffAccessContext $context, bool $managerRequired = true): ?User
    {
        if ($context->source === 'console') {
            return null;
        }

        $actor = User::query()->find($context->actorId);

        if (! $actor || ! $actor->isActiveStaff() || $actor->auth_version !== $context->authVersion
            || ($managerRequired && ! $actor->hasPermission(StaffPermission::ManageStaff))) {
            throw new AuthorizationException;
        }

        if (! $context->verifies($actor)) {
            throw ValidationException::withMessages(['current_pin' => 'PIN atual inválido.']);
        }

        return $actor;
    }

    private function protectLastManager(User $account, ?StaffRole $nextRole, bool $nextActive): void
    {
        if ($account->role !== StaffRole::Manager->value || ! $account->isActiveStaff()
            || ($nextRole === StaffRole::Manager && $nextActive)) {
            return;
        }

        $count = User::query()->where('role', StaffRole::Manager->value)
            ->where('is_active', true)->whereNotNull('username')->whereNotNull('pin_hash')->count();

        if ($count <= 1) {
            throw ValidationException::withMessages(['account' => 'Mantenha pelo menos um gestor ativo.']);
        }
    }

    private function validateIdentity(string $name, string $username, ?User $account = null): void
    {
        Validator::make(['name' => $name, 'username' => $username], [
            'name' => ['required', 'string', 'max:255'],
            'username' => [...StaffCredentials::usernameRules(), Rule::unique('users', 'username')->ignore($account?->id)],
        ])->validate();
    }

    private function validatePin(#[\SensitiveParameter] string $pin, #[\SensitiveParameter] string $confirmation): void
    {
        Validator::make(['pin' => $pin, 'pin_confirmation' => $confirmation], [
            'pin' => [...StaffCredentials::pinRules(), 'confirmed'],
        ])->validate();
    }

    private function replacePin(User $account, #[\SensitiveParameter] string $pin): void
    {
        $account->forceFill([
            'pin_hash' => $pin,
            'auth_version' => $account->auth_version + 1,
            'remember_token' => null,
        ])->save();
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        return $user->only(['name', 'username', 'role', 'is_active']);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $user, string $action, StaffAccessContext $context, ?array $before): void
    {
        DB::table('staff_access_events')->insert([
            'user_id' => $user->id,
            'actor_id' => $context->actorId,
            'action' => $action,
            'source' => $context->source,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($this->snapshot($user), JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }

    /** @param Closure(): User $operation */
    private function mutate(Closure $operation): User
    {
        try {
            return DB::transaction(function () use ($operation): User {
                DB::select('select pg_advisory_xact_lock(?)', [self::MUTATION_LOCK]);

                return $operation();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Este usuário já existe.']);
        }
    }
}
