import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search, Users as UsersIcon, UserCheck, Eye, Edit, Filter, ArrowUpDown } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
];

interface User {
    id: number;
    name: string;
    email: string;
    phone: string;
    email_verified_at: string | null;
    created_at: string;
    attempts_count: number;
    total_score: number;
    is_admin: boolean;
    role?: string;
}

interface UsersIndexProps {
    users: {
        data: User[];
        links: any[];
        meta: any;
    };
    filters: {
        search?: string;
        role?: string;
        sort?: string;
    };
    stats: {
        total_users: number;
        normal_users: number;
    };
    availableRoles: string[];
}

export default function UsersIndex({ users, filters, stats, availableRoles }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [roleFilter, setRoleFilter] = useState(filters.role || 'all');
    const [sortOption, setSortOption] = useState(filters.sort || 'default');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/users', {
            search,
            role: roleFilter !== 'all' ? roleFilter : undefined,
            sort: sortOption !== 'default' ? sortOption : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleRoleChange = (value: string) => {
        setRoleFilter(value);
        router.get('/users', {
            search: search || undefined,
            role: value !== 'all' ? value : undefined,
            sort: sortOption !== 'default' ? sortOption : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSortChange = (value: string) => {
        setSortOption(value);
        router.get('/users', {
            search: search || undefined,
            role: roleFilter !== 'all' ? roleFilter : undefined,
            sort: value !== 'default' ? value : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setSearch('');
        setRoleFilter('all');
        setSortOption('default');
        router.get('/users');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Users</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage users and view their statistics
                        </p>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Users
                            </CardTitle>
                            <UsersIcon className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_users}</div>
                            <p className="text-xs text-muted-foreground">
                                All registered users
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Normal Users
                            </CardTitle>
                            <UserCheck className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.normal_users}</div>
                            <p className="text-xs text-muted-foreground">
                                Non-admin users
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Reserved
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-muted-foreground">-</div>
                            <p className="text-xs text-muted-foreground">
                                Future use
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters and Search */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="space-y-4">
                            {/* Filter and Sort Row */}
                            <div className="grid gap-4 md:grid-cols-2">
                                {/* Role Filter */}
                                <div className="space-y-2">
                                    <Label htmlFor="role-filter" className="flex items-center gap-2">
                                        <Filter className="h-4 w-4" />
                                        Filter by Role
                                    </Label>
                                    <Select value={roleFilter} onValueChange={handleRoleChange}>
                                        <SelectTrigger id="role-filter">
                                            <SelectValue placeholder="All Roles" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Roles</SelectItem>
                                            {availableRoles.map((role) => (
                                                <SelectItem key={role} value={role}>
                                                    {role.charAt(0).toUpperCase() + role.slice(1)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Sort Options */}
                                <div className="space-y-2">
                                    <Label htmlFor="sort-option" className="flex items-center gap-2">
                                        <ArrowUpDown className="h-4 w-4" />
                                        Sort By
                                    </Label>
                                    <Select value={sortOption} onValueChange={handleSortChange}>
                                        <SelectTrigger id="sort-option">
                                            <SelectValue placeholder="Latest First (Default)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="default">Latest First (Default)</SelectItem>
                                            <SelectItem value="score_desc">Total Score (High to Low)</SelectItem>
                                            <SelectItem value="score_asc">Total Score (Low to High)</SelectItem>
                                            <SelectItem value="attempts_desc">Attempts (High to Low)</SelectItem>
                                            <SelectItem value="attempts_asc">Attempts (Low to High)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Search Bar */}
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="text"
                                        placeholder="Search by name, email, or phone..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-9"
                                    />
                                </div>
                                <Button type="submit">Search</Button>
                                {(filters.search || filters.role !== 'all' || filters.sort) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleClearFilters}
                                    >
                                        Clear All
                                    </Button>
                                )}
                            </form>
                        </div>
                    </CardContent>
                </Card>

                {/* Users Table */}
                <div className="rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Phone
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Total Score
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Attempts
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 text-left text-sm font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-8 text-center text-sm text-muted-foreground"
                                        >
                                            No users found.
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-muted/50">
                                            <td className="px-4 py-3 text-sm font-medium">
                                                {user.name}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-muted-foreground">
                                                {user.email}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-muted-foreground">
                                                {user.phone}
                                            </td>
                                            <td className="px-4 py-3 text-sm font-semibold">
                                                {user.total_score}
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                {user.attempts_count}
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                <span
                                                    className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${
                                                        user.role === 'admin'
                                                            ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                                    }`}
                                                >
                                                    {user.role
                                                        ? user.role.charAt(0).toUpperCase() + user.role.slice(1)
                                                        : 'User'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link href={`/users/${user.id}`}>
                                                            <Eye className="mr-1 h-3 w-3" />
                                                            View
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link href={`/users/${user.id}/edit`}>
                                                            <Edit className="mr-1 h-3 w-3" />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                </div>
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

