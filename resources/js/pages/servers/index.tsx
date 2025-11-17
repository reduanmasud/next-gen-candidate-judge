import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { PlusIcon, Server } from 'lucide-react';
import { useEcho } from '@laravel/echo-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Servers',
        href: '/servers',
    },
];

interface ServerType {
    id: number;
    name: string;
    ip_address: string;
    status: string;
    provisioned_at: string | null;
    created_at: string;
    user?: {
        name: string;
    };
}

interface ServersIndexProps {
    servers: {
        data: ServerType[];
        links: any[];
        meta: any;
    };
}

type ServerCreatedEvent = {
    server: ServerType;
};

type ServerStatusEvent = {
    serverId: number;
    status: string;
    currentStep: string | null;
    metadata: Record<string, any> | null;
};

export default function ServersIndex({ servers: initialServers }: ServersIndexProps) {
    const [servers, setServers] = useState(initialServers);

    // Listen for new server creation
    useEcho<ServerCreatedEvent>('server-updates', 'ServerCreatedEvent', (event) => {
        console.log('🔔 New server created:', event);

        setServers(prev => {
            console.log('Adding new server to list');
            return {
                ...prev,
                data: [event.server, ...prev.data]
            };
        });
    });

    // Listen for server status updates
    useEcho<ServerStatusEvent>('server-updates', 'ServerProvisioningStatusUpdatedEvent', (event) => {
        console.log('🔔 Server status updated:', event);
        console.log('Updating server ID:', event.serverId, 'to status:', event.status);

        setServers(prev => ({
            ...prev,
            data: prev.data.map(server => {
                if (server.id === event.serverId) {
                    console.log('✅ Found server in list, updating status');
                    return { ...server, status: event.status };
                }
                return server;
            })
        }));
    });

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

        const config = statusConfig[status] || statusConfig.pending;

        return (
            <span
                className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${config.className}`}
            >
                {config.label}
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Server Provision" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Server Provision</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage and provision servers for workspace hosting
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/servers/create">
                            <PlusIcon className="mr-2 h-4 w-4" />
                            Provision Server
                        </Link>
                    </Button>
                </div>

                <div className="rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        IP Address
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Provisioned At
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {servers.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-sm text-muted-foreground"
                                        >
                                            No servers found. Provision your first server to get started.
                                        </td>
                                    </tr>
                                ) : (
                                    servers.data.map((server) => (
                                        <tr key={server.id} className="hover:bg-muted/50">
                                            <td className="px-4 py-3 text-sm font-medium">
                                                <div className="flex items-center gap-2">
                                                    <Server className="h-4 w-4 text-muted-foreground" />
                                                    {server.name}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-sm font-mono text-muted-foreground">
                                                {server.ip_address}
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                {getStatusBadge(server.status)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-muted-foreground">
                                                {server.provisioned_at
                                                    ? new Date(server.provisioned_at).toLocaleString()
                                                    : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link href={`/servers/${server.id}`}>
                                                        View
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

