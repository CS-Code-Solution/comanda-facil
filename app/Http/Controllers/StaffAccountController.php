<?php

namespace App\Http\Controllers;

use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffCredentials;
use App\Enums\StaffRole;
use App\Http\Presenters\StaffAudit;
use App\Http\Requests\Staff\ResetStaffPinRequest;
use App\Http\Requests\Staff\StaffManagementRequest;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Support\RefreshStaffSession;
use App\Models\StaffAccessEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = $request->string('q')->trim()->toString();
        $accounts = User::query()->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter
            ->where('name', 'ilike', '%'.$search.'%')->orWhere('username', 'ilike', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return Inertia::render('staff/index', [
            'accounts' => [
                'data' => $accounts->getCollection()->map(fn (User $user): array => $this->account($user))->values(),
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'total' => $accounts->total(),
                'previous' => $accounts->previousPageUrl(),
                'next' => $accounts->nextPageUrl(),
            ],
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('staff/create', ['roles' => $this->roles(), 'pinLength' => StaffCredentials::PIN_LENGTH]);
    }

    public function store(StoreStaffRequest $request, ManageStaffAccount $accounts): RedirectResponse
    {
        $user = $accounts->create($request->string('name')->toString(), $request->string('username')->toString(),
            StaffRole::from($request->string('role')->toString()), $request->string('pin')->toString(),
            $request->string('pin_confirmation')->toString(), $request->accessContext());

        return $this->done($user, 'Conta criada.');
    }

    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('staff/edit', [
            'account' => $this->account($user),
            'roles' => $this->roles(),
            'pinLength' => StaffCredentials::PIN_LENGTH,
            'canChangeRole' => $request->user()?->id !== $user->id,
            'canChangeStatus' => $request->user()?->id !== $user->id,
            'sessionActionLabel' => $request->user()?->id === $user->id ? 'Encerrar outras sessões' : 'Revogar sessões',
            'events' => StaffAccessEvent::query()->where('user_id', $user->id)->with('actor:id,name')
                ->orderByDesc('id')->limit(20)->get()->map(fn (StaffAccessEvent $event): array => StaffAudit::present($event)),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $user, ManageStaffAccount $accounts): RedirectResponse
    {
        $account = $accounts->update($user, $request->string('name')->toString(), $request->string('username')->toString(),
            StaffRole::from($request->string('role')->toString()), $request->accessContext());
        RefreshStaffSession::forOwnAccount($request, $account);

        return $this->done($account, 'Cadastro atualizado.');
    }

    public function resetPin(ResetStaffPinRequest $request, User $user, ManageStaffAccount $accounts): RedirectResponse
    {
        $account = $accounts->resetPin($user, $request->string('pin')->toString(), $request->string('pin_confirmation')->toString(), $request->accessContext());
        RefreshStaffSession::forOwnAccount($request, $account);

        return $this->done($account, 'PIN redefinido. Sessões anteriores revogadas.');
    }

    public function activate(StaffManagementRequest $request, User $user, ManageStaffAccount $accounts): RedirectResponse
    {
        return $this->done($accounts->setActive($user, true, $request->accessContext()), 'Conta ativada.');
    }

    public function disable(StaffManagementRequest $request, User $user, ManageStaffAccount $accounts): RedirectResponse
    {
        return $this->done($accounts->disable($user, $request->accessContext()), 'Conta desativada.');
    }

    public function revokeSessions(StaffManagementRequest $request, User $user, ManageStaffAccount $accounts): RedirectResponse
    {
        $account = $accounts->revokeSessions($user, $request->accessContext());
        RefreshStaffSession::forOwnAccount($request, $account);

        return $this->done($account, 'Sessões anteriores revogadas.');
    }

    /** @return array<string, mixed> */
    private function account(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'username', 'role', 'is_active']),
            'role_label' => StaffRole::tryFrom($user->role ?? '')?->label() ?? 'Sem papel',
            'status_label' => $user->isActiveStaff() ? 'Ativo' : ($user->is_active ? 'Cadastro incompleto' : 'Inativo'),
            'edit_url' => route('staff.edit', $user, absolute: false),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function roles(): array
    {
        return array_map(fn (StaffRole $role): array => ['value' => $role->value, 'label' => $role->label()], StaffRole::cases());
    }

    private function done(User $account, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('staff.edit', $account);
    }
}
