import { useState } from 'react';

import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import { ENROLLMENT_STEPS } from '@/types/radar';
import type { ContestDto } from '@/types/radar';

type EnrollmentChecklistProps = {
    contest: ContestDto;
    onChange: (contest: ContestDto) => void;
};

/**
 * Checklist vertical de "minha situação" — docs/03-ui-inventario.md → "Modal
 * do dossiê → Minha situação". Clicar num passo define o enum (marca os
 * anteriores, desmarca os seguintes) porque o enum é sequencial: um único
 * valor já representa isso. Desabilitado quando o concurso está descartado.
 */
export function EnrollmentChecklist({ contest, onChange }: EnrollmentChecklistProps) {
    const [saving, setSaving] = useState<string | null>(null);
    const currentIndex = ENROLLMENT_STEPS.findIndex((s) => s.value === contest.enrollment_status);
    const disabled = contest.enrollment_status === 'dismissed';

    async function setStep(value: string) {
        if (disabled || saving) {
            return;
        }

        setSaving(value);

        const response = await csrfFetch(`/contests/${contest.id}/enrollment`, {
            method: 'PATCH',
            body: JSON.stringify({ status: value }),
        });

        setSaving(null);

        if (!response.ok) {
            const error: { message?: string } = await response.json().catch(() => ({}));
            notifyError(error.message ?? 'Não deu pra salvar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onChange(data.contest);
    }

    return (
        <ul className="space-y-1">
            {ENROLLMENT_STEPS.map((step, i) => {
                const done = !disabled && currentIndex >= 0 && i <= currentIndex;

                return (
                    <li key={step.value}>
                        <button
                            type="button"
                            disabled={disabled || saving !== null}
                            onClick={() => setStep(step.value)}
                            role="checkbox"
                            aria-checked={done}
                            aria-current={i === currentIndex && !disabled ? 'step' : undefined}
                            className="t-body flex min-h-7 w-full items-center gap-2 text-left disabled:cursor-not-allowed"
                        >
                            <span
                                aria-hidden="true"
                                className={`flex h-5 w-5 shrink-0 items-center justify-center border-2 text-xs font-bold ${
                                    done ? 'border-red-text bg-red-text text-paper' : 'border-line-strong text-muted-fg'
                                }`}
                            >
                                {done ? '✕' : ''}
                            </span>
                            <span className={done ? 'font-semibold text-ink' : 'text-muted-fg'}>
                                {step.label}
                                {i === currentIndex && !disabled ? ' (atual)' : ''}
                            </span>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}
