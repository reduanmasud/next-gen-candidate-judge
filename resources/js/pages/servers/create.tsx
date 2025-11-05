import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { useForm, Head, Link } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Server } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Servers',
        href: '/servers',
    },
    {
        title: 'Provision Server',
        href: '/servers/create',
    },
];

export default function CreateServer() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        ip_address: '',
        ssh_username: 'root',
        ssh_password: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/servers');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Provision Server" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card className="rounded-xl">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Server className="h-5 w-5" />
                            <CardTitle className="text-2xl">Provision New Server</CardTitle>
                        </div>
                        <CardDescription>
                            Enter the server details to provision it with Docker and required dependencies
                        </CardDescription>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <form onSubmit={handleSubmit} className="grid gap-8 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Server Name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., Production Server 1"
                                        required
                                        autoFocus
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        A descriptive name to identify this server.
                                    </p>
                                    <InputError message={errors.name} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="ip_address">Server IP Address</Label>
                                    <Input
                                        id="ip_address"
                                        type="text"
                                        value={data.ip_address}
                                        onChange={(e) => setData('ip_address', e.target.value)}
                                        placeholder="192.168.1.100"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The IP address of the server to provision. Must be accessible via SSH.
                                    </p>
                                    <InputError message={errors.ip_address} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="ssh_username">SSH Username</Label>
                                    <Input
                                        id="ssh_username"
                                        type="text"
                                        value={data.ssh_username}
                                        onChange={(e) => setData('ssh_username', e.target.value)}
                                        placeholder="root or another user"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The SSH user to connect as (default is root).
                                    </p>
                                    <InputError message={errors.ssh_username} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="ssh_password">SSH Password</Label>
                                    <Input
                                        id="ssh_password"
                                        type="password"
                                        value={data.ssh_password}
                                        onChange={(e) => setData('ssh_password', e.target.value)}
                                        placeholder="Enter SSH password"
                                        required
                                        minLength={8}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The SSH password for the specified user. Minimum 8 characters.
                                    </p>
                                    <InputError message={errors.ssh_password} />
                                </div>
                            </div>

                            <div className="space-y-6 lg:col-span-1">
                                <div className="rounded-lg border bg-muted/50 p-4">
                                    <h3 className="mb-2 text-sm font-semibold">Provisioning Steps</h3>
                                    <ul className="space-y-2 text-xs text-muted-foreground">
                                        <li className="flex items-start gap-2">
                                            <span className="mt-1">1.</span>
                                            <span>Test SSH connection to server</span>
                                        </li>
                                        <li className="flex items-start gap-2">
                                            <span className="mt-1">2.</span>
                                            <span>Install Docker and Docker Compose</span>
                                        </li>
                                        <li className="flex items-start gap-2">
                                            <span className="mt-1">3.</span>
                                            <span>Configure Docker service</span>
                                        </li>
                                        <li className="flex items-start gap-2">
                                            <span className="mt-1">4.</span>
                                            <span>Verify installation</span>
                                        </li>
                                    </ul>
                                </div>

                                <Separator />

                                <div className="flex flex-col gap-3 sm:flex-row">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Provisioning...' : 'Provision Server'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href="/servers">Cancel</Link>
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

