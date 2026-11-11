import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { RadarMark } from '@/components/radar/RadarMark';

export type RadarSection = 'mesa' | 'painel' | 'arquivo' | 'conta';

const NAV: { section: RadarSection; label: string; href: string }[] = [
    { section: 'mesa', label: 'mesa', href: '/' },
    { section: 'painel', label: 'painel', href: '/dashboard' },
    { section: 'arquivo', label: 'arquivo', href: '/archive' },
    { section: 'conta', label: 'conta', href: '/settings/profile' },
];

type RadarHeaderProps = {
    title: string;
    current: RadarSection;
    /** Controles da tela (ex.: varredura na mesa), numa segunda linha. */
    children?: ReactNode;
};

/**
 * Masthead única de todas as telas autenticadas: marca + título + a mesma
 * navegação, na mesma ordem, com a seção atual marcada (aria-current).
 */
export function RadarHeader({ title, current, children }: RadarHeaderProps) {
    return (
        <header className="shrink-0 border-b border-line-strong bg-paper px-6 py-3">
            <div className="flex flex-wrap items-center gap-4">
                <RadarMark size={28} />
                <h1 className="text-display">{title}</h1>
                <nav aria-label="Seções" className="ml-auto">
                    <ul className="flex items-center gap-4">
                        {NAV.map((item) => {
                            const active = item.section === current;

                            return (
                                <li key={item.section}>
                                    <Link
                                        href={item.href}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={`t-body inline-flex min-h-6 items-center underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ${
                                            active
                                                ? 'font-semibold text-ink underline decoration-2'
                                                : 'text-muted-fg hover:text-ink hover:underline'
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>
            </div>
            {children && <div className="mt-3">{children}</div>}
        </header>
    );
}
