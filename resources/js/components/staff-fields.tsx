import { useId } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type RoleOption = { value: string; label: string };
export type StaffAccount = {
    id: number;
    name: string;
    username: string | null;
    role: string | null;
    role_label: string;
    is_active: boolean;
    status_label: string;
    edit_url: string;
};
export type AuditEvent = {
    id: number;
    action: string;
    actor: string;
    source: string;
    occurred_at: string;
    changes: { field: string; before: string; after: string }[];
};

export function IdentityFields({
    roles,
    errors,
    account,
    canChangeRole = true,
}: {
    roles: RoleOption[];
    errors: Record<string, string>;
    account?: StaffAccount;
    canChangeRole?: boolean;
}) {
    const id = useId();

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={id + '-name'}>Nome</Label>
                <Input
                    id={id + '-name'}
                    name="name"
                    required
                    maxLength={255}
                    defaultValue={account?.name}
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id + '-username'}>Usuário</Label>
                <Input
                    id={id + '-username'}
                    name="username"
                    required
                    autoCapitalize="none"
                    spellCheck={false}
                    autoComplete="off"
                    defaultValue={account?.username ?? ''}
                />
                <InputError message={errors.username} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={id + '-role'}>Papel</Label>
                {canChangeRole ? (
                    <Select
                        name="role"
                        defaultValue={account?.role ?? undefined}
                        required
                    >
                        <SelectTrigger id={id + '-role'} className="w-full">
                            <SelectValue placeholder="Selecione um papel" />
                        </SelectTrigger>
                        <SelectContent>
                            {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ) : (
                    <>
                        <input
                            type="hidden"
                            name="role"
                            value={account?.role ?? ''}
                        />
                        <p className="text-sm">{account?.role_label}</p>
                    </>
                )}
                <InputError message={errors.role} />
            </div>
        </>
    );
}

export function PinFields({
    errors,
    pinLength,
    includeNew = false,
}: {
    errors: Record<string, string>;
    pinLength: number;
    includeNew?: boolean;
}) {
    const id = useId();
    return (
        <>
            {includeNew && (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={id + '-pin'}>Novo PIN</Label>
                        <Input
                            id={id + '-pin'}
                            name="pin"
                            type="password"
                            inputMode="numeric"
                            minLength={pinLength}
                            maxLength={pinLength}
                            autoComplete="new-password"
                            required
                        />
                        <InputError message={errors.pin} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={id + '-confirmation'}>
                            Confirme o novo PIN
                        </Label>
                        <Input
                            id={id + '-confirmation'}
                            name="pin_confirmation"
                            type="password"
                            inputMode="numeric"
                            minLength={pinLength}
                            maxLength={pinLength}
                            autoComplete="new-password"
                            required
                        />
                        <InputError message={errors.pin_confirmation} />
                    </div>
                </>
            )}
            <div className="grid gap-2">
                <Label htmlFor={id + '-current'}>Seu PIN atual</Label>
                <Input
                    id={id + '-current'}
                    name="current_pin"
                    type="password"
                    inputMode="numeric"
                    minLength={pinLength}
                    maxLength={pinLength}
                    autoComplete="current-password"
                    required
                />
                <InputError message={errors.current_pin} />
            </div>
            <InputError message={errors.account} />
        </>
    );
}
