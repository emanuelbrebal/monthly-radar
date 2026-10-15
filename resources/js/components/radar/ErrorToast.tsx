import { useEffect, useRef, useState } from 'react';

import { onNotifyError } from '@/lib/notify';

/** Toast de erro genérico pra falha de ação (pin, checklist, reorder…) — docs/03-ui-inventario.md microcopy "erro geral". */
export function ErrorToast() {
    const [message, setMessage] = useState<string | null>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return onNotifyError((msg) => {
            if (timer.current) {
                clearTimeout(timer.current);
            }

            setMessage(msg);
            timer.current = setTimeout(() => setMessage(null), 6000);
        });
    }, []);

    if (message === null) {
        return null;
    }

    return (
        <div
            role="alert"
            aria-live="assertive"
            className="surface-card t-body fixed bottom-4 left-4 flex items-center gap-3 border-2 border-red-text px-3 py-2 text-red-text shadow-[4px_4px_0_rgba(26,22,18,0.18)]"
        >
            <span>{message}</span>
            <button
                type="button"
                aria-label="Fechar aviso de erro"
                className="inline-flex min-h-6 min-w-6 items-center justify-center px-1 underline"
                onClick={() => setMessage(null)}
            >
                fechar
            </button>
        </div>
    );
}
