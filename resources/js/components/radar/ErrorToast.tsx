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
        <div className="rm-mono fixed bottom-4 left-4 flex items-center gap-3 rounded-md border border-red bg-card px-3 py-2 text-[11px] text-red shadow-[4px_4px_0_rgba(26,22,18,0.18)]">
            {message}
            <button type="button" className="underline" onClick={() => setMessage(null)}>
                fechar
            </button>
        </div>
    );
}
