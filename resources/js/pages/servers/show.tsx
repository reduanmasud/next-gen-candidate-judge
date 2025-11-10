import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Server, Calendar, User, Network } from 'lucide-react';
import { useEffect, useState } from 'react';
import ServerProvisionProgressTracker from '@/components/ServerProvisionProgressTracker';

interface ServerType {
    id: number;
    name: string;
    ip_address: string;
    status: string;
    provisioned_at: string | null;
    notes: string | null;
    created_at: string;
    user?: {
        name: string;
    };
}

interface ServerShowProps {
    server: ServerType;
    metadata?: Record<string, any>;
}

export default function ServerShow({ server, metadata }: ServerShowProps) {
    const [currentStep, setCurrentStep] = useState<string | null>(metadata?.current_step || null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Servers',
            href: '/servers',
        },
        {
            title: server.name,
            href: `/servers/${server.id}`,
        },
    ];

    // Poll for status updates when server is provisioning
    useEffect(() => {
        if (server.status !== 'provisioning') return;

        const pollInterval = setInterval(() => {
            fetch(`/servers/${server.id}/status`)
                .then(res => res.json())
                .then(data => {
                    setCurrentStep(data.current_step);

                    // If status changed, reload the page to get updated server data
                    if (data.status !== 'provisioning') {
                        router.reload({ only: ['server', 'metadata'] });
                    }
                })
                .catch(err => console.error('Failed to fetch status:', err));
        }, 2000); // Poll every 2 seconds

        return () => clearInterval(pollInterval);
    }, [server.id, server.status]);

    const getStatusBadge = (status: string) => {
        const statusConfig: Record<string, { label: string; className: string }> = {
            pending: {
                label: 'Pending',
                className: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            },
            provisioning: {
                label: 'Provisioning',
                className: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            },
            provisioned: {
                label: 'Provisioned',
                className: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            },
            failed: {
                label: 'Failed',
                className: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            },
        };

        const config = statusConfig[server.status] || statusConfig.pending;

        return (
            <span
                className={`inline-flex rounded-full px-3 py-1 text-sm font-medium ${config.className}`}
            >
                {config.label}
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={server.name} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-auto p-4">
                {server.status === 'provisioning' ? (
                    <div className="flex h-full w-full items-center justify-center">
                        <div className="flex w-full max-w-lg flex-col gap-6 rounded-lg border bg-card px-8 py-8 shadow-lg">
                            <div className="text-center">
                                <p className="text-xl font-bold mb-2">
                                    {server.name}
                                </p>
                                <p className="text-lg font-semibold">
                                    Provisioning your server…
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Setting up Docker and required dependencies. This may take several minutes.
                                </p>
                            </div>

                            <Separator />

                            <ServerProvisionProgressTracker currentStep={currentStep} />
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-2xl font-semibold">{server.name}</h1>
                                <p className="text-sm text-muted-foreground">
                                    Server provisioning details
                                </p>
                            </div>
                            <Button variant="outline" asChild>
                                <Link href="/servers">Back to Servers</Link>
                            </Button>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <Card className="rounded-xl">
                            <CardHeader>
                                <CardTitle>Server Information</CardTitle>
                                <CardDescription>
                                    Details about the provisioned server
                                </CardDescription>
                            </CardHeader>
                            <Separator />
                            <CardContent className="pt-6">
                                <div className="space-y-6">
                                    <div className="flex items-start gap-4">
                                        <Server className="mt-1 h-5 w-5 text-muted-foreground" />
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">Server Name</p>
                                            <p className="text-sm text-muted-foreground">{server.name}</p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-4">
                                        <Network className="mt-1 h-5 w-5 text-muted-foreground" />
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">IP Address</p>
                                            <p className="font-mono text-sm text-muted-foreground">
                                                {server.ip_address}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-4">
                                        <div className="mt-1 h-5 w-5" />
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">Status</p>
                                            <div className="mt-1">{getStatusBadge(server.status)}</div>
                                        </div>
                                    </div>

                                    {server.user && (
                                        <div className="flex items-start gap-4">
                                            <User className="mt-1 h-5 w-5 text-muted-foreground" />
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">Provisioned By</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {server.user.name}
                                                </p>
                                            </div>
                                        </div>
                                    )}

                                    {server.provisioned_at && (
                                        <div className="flex items-start gap-4">
                                            <Calendar className="mt-1 h-5 w-5 text-muted-foreground" />
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">Provisioned At</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {new Date(server.provisioned_at).toLocaleString()}
                                                </p>
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex items-start gap-4">
                                        <Calendar className="mt-1 h-5 w-5 text-muted-foreground" />
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">Created At</p>
                                            <p className="text-sm text-muted-foreground">
                                                {new Date(server.created_at).toLocaleString()}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="lg:col-span-1">
                        <Card className="rounded-xl">
                            <CardHeader>
                                <CardTitle>Notes</CardTitle>
                                <CardDescription>Provisioning logs and notes</CardDescription>
                            </CardHeader>
                            <Separator />
                            <CardContent className="pt-6">
                                {server.notes ? (
                                    <pre className="max-h-96 overflow-auto rounded-lg bg-muted p-4 text-xs font-mono whitespace-pre-wrap">
                                        {server.notes}
                                    </pre>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No notes available</p>
                                )}
                            </CardContent>
                        </Card>
                        </div>
                    </div>
                </>
                )}
            </div>
        </AppLayout>
    );
}

