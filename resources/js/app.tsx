import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    // As páginas do radar já trazem "· Radar Mensal" no título — não repetir.
    title: (title) =>
        !title
            ? appName
            : title.includes(appName)
              ? title
              : `${title} · ${appName}`,
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            // Conta usa o chrome do radar (RadarHeader), sem a sidebar.
            case name.startsWith('settings/'):
                return SettingsLayout;
            // A Mesa é full-bleed (masthead própria) — sem sidebar do starter kit.
            case name.startsWith('radar/'):
                return undefined;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#9a4434', // --red do app.css
    },
});

// This will set light / dark mode on load...
initializeTheme();
