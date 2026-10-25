import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { edit } from '@/routes/profile';

export default function Profile() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Minha conta" />
            <Heading
                variant="small"
                title="Minha conta"
                description="Identificação da sua conta individual."
            />
            <dl className="grid gap-4 text-sm">
                <div>
                    <dt className="text-muted-foreground">Nome</dt>
                    <dd className="font-medium">{auth.user.name}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Usuário</dt>
                    <dd className="font-medium">{auth.user.username}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Papel</dt>
                    <dd className="font-medium">{auth.user.role_label}</dd>
                </div>
            </dl>
            <p className="text-sm text-muted-foreground">
                Para corrigir seus dados ou redefinir seu PIN, procure o
                responsável pelo sistema.
            </p>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [{ title: 'Minha conta', href: edit() }],
};
