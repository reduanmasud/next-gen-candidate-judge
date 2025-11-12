import { SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const { sidebarOpen, flash } = usePage<SharedData>().props;
    const previousFlash = useRef<typeof flash>({});

    // Show flash messages
    useEffect(() => {
        if (flash.success && flash.success !== previousFlash.current.success) {
            toast.success(flash.success);
        }
        if (flash.error && flash.error !== previousFlash.current.error) {
            toast.error(flash.error);
        }
        if (flash.warning && flash.warning !== previousFlash.current.warning) {
            toast.warning(flash.warning);
        }
        if (flash.info && flash.info !== previousFlash.current.info) {
            toast.info(flash.info);
        }

        previousFlash.current = flash;
    }, [flash.success, flash.error, flash.warning, flash.info]);

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">
                {children}
                <Toaster />
            </div>
        );
    }

    return (
        <SidebarProvider defaultOpen={sidebarOpen}>
            {children}
            <Toaster />
        </SidebarProvider>
    );
}
