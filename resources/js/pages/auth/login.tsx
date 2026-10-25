import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

type Props = {
    pinLength: number;
};

export default function Login({ pinLength }: Props) {
    return (
        <>
            <Head title="Acesso da equipe" />
            <Form
                {...store.form()}
                resetOnError={['pin']}
                resetOnSuccess={['pin']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="username">Usuário</Label>
                            <Input
                                id="username"
                                name="username"
                                required
                                autoFocus
                                autoComplete="username"
                                autoCapitalize="none"
                                spellCheck={false}
                                placeholder="Seu usuário"
                            />
                            <InputError message={errors.username} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pin">PIN</Label>
                            <Input
                                id="pin"
                                name="pin"
                                type="password"
                                inputMode="numeric"
                                minLength={pinLength}
                                maxLength={pinLength}
                                autoComplete="current-password"
                                required
                                placeholder={pinLength + ' dígitos'}
                            />
                            <InputError message={errors.pin} />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Entrar
                        </Button>
                        <p className="text-center text-sm text-muted-foreground">
                            Para receber acesso ou redefinir seu PIN, procure o
                            responsável pelo sistema.
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Acesso da equipe',
    description: 'Entre com sua conta individual e PIN.',
};
