import { Link } from '@inertiajs/react';
import { RadarMark } from '@/components/radar/RadarMark';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/** Telas de login/senha no mesmo papel & tinta da mesa: marca, título display e um cartão. */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-paper p-6 text-ink">
            <div className="flex w-full max-w-sm flex-col gap-6">
                <Link
                    href={home()}
                    className="t-body flex items-center justify-center gap-2 text-muted-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    <RadarMark size={28} />
                    radar mensal · concursos
                </Link>

                <div className="surface-card flex flex-col gap-6 rounded-lg border border-ink p-6">
                    <div className="flex flex-col gap-2">
                        <h1 className="rm-display text-display">{title}</h1>
                        {description && (
                            <p className="t-body text-muted-fg">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
