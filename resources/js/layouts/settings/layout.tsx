import { Head, Link, router } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { RadarHeader } from '@/components/radar/RadarHeader';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

// "aparência" saiu da navegação: o app é sempre claro (app.css não tem
// bloco .dark), então a tela não tinha efeito. A rota continua existindo.
const tabs: NavItem[] = [
    { title: 'perfil', href: edit(), icon: null },
    { title: 'segurança', href: editSecurity(), icon: null },
];

/** Conta no mesmo chrome da mesa (RadarHeader), sem a sidebar do starter kit. */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <>
            <Head title="Conta · Radar Mensal" />
            <div className="flex min-h-screen flex-col bg-paper text-ink">
                <RadarHeader title="conta" current="conta" />

                <main className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-6">
                    <nav aria-label="Conta" className="flex gap-2">
                        {tabs.map((tab) => {
                            const active = isCurrentOrParentUrl(tab.href);

                            return (
                                <Link
                                    key={toUrl(tab.href)}
                                    href={tab.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={`t-body min-h-6 rounded-sm border px-2 py-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ${
                                        active
                                            ? 'border-ink font-semibold'
                                            : 'border-line-strong text-muted-fg hover:text-ink'
                                    }`}
                                >
                                    {tab.title}
                                </Link>
                            );
                        })}

                        {/* A sidebar do starter kit (que tinha o "sair") saiu;
                            o logout mora aqui, na conta. */}
                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => router.flushAll()}
                            data-test="logout-button"
                            className="t-body ml-auto min-h-6 px-2 py-1 text-muted-fg underline-offset-4 hover:text-ink hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            sair
                        </Link>
                    </nav>

                    <section className="surface-card flex flex-col gap-6 rounded-lg border border-ink p-6">
                        {children}
                    </section>
                </main>
            </div>
        </>
    );
}
