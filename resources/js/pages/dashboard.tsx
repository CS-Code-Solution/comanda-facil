import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { toUrl } from '@/lib/utils';

export default function Dashboard() {
    const { auth, navigation } = usePage().props;
    return (
        <>
            <Head title="Início" />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title={'Olá, ' + auth.user.name}
                    description={
                        auth.user.role_label + ' · ' + auth.user.username
                    }
                />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {navigation.map((item) => (
                        <Link
                            key={toUrl(item.href)}
                            href={item.href}
                            className="rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <Card className="h-full transition-colors hover:bg-accent">
                                <CardHeader>
                                    <CardTitle>{item.title}</CardTitle>
                                    <CardDescription>Acessar</CardDescription>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}
Dashboard.layout = { breadcrumbs: [{ title: 'Início', href: dashboard() }] };
