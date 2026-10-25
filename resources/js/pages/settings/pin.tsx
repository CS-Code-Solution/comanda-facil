import { Form, Head } from '@inertiajs/react';
import OwnPinController from '@/actions/App/Http/Controllers/OwnPinController';
import Heading from '@/components/heading';
import { PinFields } from '@/components/staff-fields';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/pin';

export default function OwnPin({ pinLength }: { pinLength: number }) {
    return (
        <>
            <Head title="Meu PIN" />
            <Heading
                variant="small"
                title="Alterar meu PIN"
                description="Confirme com seu PIN atual. Outras sessões serão encerradas."
            />
            <Form
                {...OwnPinController.update.form()}
                resetOnError={['pin', 'pin_confirmation', 'current_pin']}
                resetOnSuccess
                className="space-y-5"
            >
                {({ errors, processing }) => (
                    <>
                        <PinFields
                            errors={errors}
                            pinLength={pinLength}
                            includeNew
                        />
                        <Button disabled={processing}>Alterar meu PIN</Button>
                    </>
                )}
            </Form>
        </>
    );
}
OwnPin.layout = { breadcrumbs: [{ title: 'Meu PIN', href: edit() }] };
