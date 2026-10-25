import { Form, Head, Link } from '@inertiajs/react';
import StaffAccountController from '@/actions/App/Http/Controllers/StaffAccountController';
import Heading from '@/components/heading';
import {
    IdentityFields,
    PinFields,
    type RoleOption,
} from '@/components/staff-fields';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/staff';

export default function CreateStaff({
    roles,
    pinLength,
}: {
    roles: RoleOption[];
    pinLength: number;
}) {
    return (
        <>
            <Head title="Nova conta" />
            <div className="max-w-2xl space-y-6 p-4 md:p-6">
                <Heading
                    title="Nova conta da equipe"
                    description="Cada pessoa deve ter seu próprio usuário e PIN."
                />
                <Form
                    {...StaffAccountController.store.form()}
                    resetOnError={['pin', 'pin_confirmation', 'current_pin']}
                    resetOnSuccess={['pin', 'pin_confirmation', 'current_pin']}
                    className="space-y-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <IdentityFields roles={roles} errors={errors} />
                            <PinFields
                                errors={errors}
                                pinLength={pinLength}
                                includeNew
                            />
                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Criar conta
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
CreateStaff.layout = {
    breadcrumbs: [
        { title: 'Equipe', href: index() },
        { title: 'Nova conta', href: '#' },
    ],
};
