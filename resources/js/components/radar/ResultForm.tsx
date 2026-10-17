import { useEffect, useId, useState } from 'react';

import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import type {
    BreakdownRow,
    ContestDto,
    ExamOutcomeValue,
    ResultSourceValue,
} from '@/types/radar';

type ResultFormProps = {
    contest: ContestDto;
    onChange: (contest: ContestDto) => void;
};

type RowDraft = { subject: string; score: string; max: string };

const OUTCOMES: { value: ExamOutcomeValue; label: string }[] = [
    { value: 'pending', label: 'aguardando' },
    { value: 'passed', label: 'aprovado' },
    { value: 'reserve', label: 'cadastro reserva' },
    { value: 'failed', label: 'eliminado' },
];

const SOURCES: { value: ResultSourceValue; label: string }[] = [
    { value: 'self_graded', label: 'gabarito' },
    { value: 'preliminary', label: 'preliminar' },
    { value: 'official', label: 'oficial' },
];

const INPUT =
    't-body min-h-7 w-full border border-line-strong bg-card px-2 py-1 text-ink tabular-nums placeholder:text-muted-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';

/** Aceita "57,5" e "57.5". Vazio → null. */
function parseNumber(value: string): number | null {
    const normalized = value.trim().replace(',', '.');

    if (normalized === '') {
        return null;
    }

    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : null;
}

function fmt(value: number | null | undefined): string {
    return value === null || value === undefined
        ? ''
        : String(value).replace('.', ',');
}

function pct(value: number): string {
    return `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

/**
 * Bloco "resultado" do dossiê — docs/03-ui-inventario.md → "Resultado (T7)".
 * Estado local inicializado do contest uma única vez: o pai remonta este
 * componente com `key` quando troca de concurso (nunca sincronizar prop →
 * useState, bug da T4).
 */
export function ResultForm({ contest, onChange }: ResultFormProps) {
    const result = contest.result;
    const listId = useId();
    const [score, setScore] = useState(fmt(result?.score));
    const [maxScore, setMaxScore] = useState(fmt(result?.max_score));
    const [cutoff, setCutoff] = useState(fmt(result?.cutoff_score));
    const [outcome, setOutcome] = useState<ExamOutcomeValue>(
        result?.outcome ?? 'pending',
    );
    const [source, setSource] = useState<ResultSourceValue>(
        result?.source ?? 'self_graded',
    );
    const [position, setPosition] = useState(fmt(result?.position));
    const [candidates, setCandidates] = useState(fmt(result?.candidates));
    const [rows, setRows] = useState<RowDraft[]>(
        (result?.breakdown ?? []).map((r) => ({
            subject: r.subject,
            score: fmt(r.score),
            max: fmt(r.max),
        })),
    );
    const [showBreakdown, setShowBreakdown] = useState(
        (result?.breakdown.length ?? 0) > 0,
    );
    const [subjects, setSubjects] = useState<string[]>([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!showBreakdown) {
            return;
        }

        csrfFetch('/results/subjects', { method: 'GET' })
            .then((r) => (r.ok ? r.json() : { subjects: [] }))
            .then((data: { subjects: string[] }) => setSubjects(data.subjects))
            .catch(() => setSubjects([]));
    }, [showBreakdown]);

    const scoreN = parseNumber(score);
    const maxN = parseNumber(maxScore);
    const cutoffN = parseNumber(cutoff);
    const preview =
        scoreN !== null && maxN !== null && maxN > 0
            ? {
                  percent: (scoreN / maxN) * 100,
                  margin: cutoffN !== null ? scoreN - cutoffN : null,
              }
            : null;

    const filledRows = rows.filter((r) => r.subject.trim() !== '');
    const rowsTotal = filledRows.reduce(
        (sum, r) => sum + (parseNumber(r.score) ?? 0),
        0,
    );
    const sumMismatch =
        filledRows.length > 0 &&
        scoreN !== null &&
        Math.abs(rowsTotal - scoreN) > 0.009;

    function updateRow(index: number, patch: Partial<RowDraft>) {
        setRows((prev) =>
            prev.map((r, i) => (i === index ? { ...r, ...patch } : r)),
        );
    }

    async function save() {
        setSaving(true);

        const breakdown: BreakdownRow[] = filledRows.map((r) => ({
            subject: r.subject.trim(),
            score: parseNumber(r.score) ?? 0,
            max: parseNumber(r.max) ?? 0,
        }));

        const response = await csrfFetch(`/contests/${contest.id}/result`, {
            method: 'PUT',
            body: JSON.stringify({
                score: scoreN,
                max_score: maxN,
                cutoff_score: cutoffN,
                outcome,
                source,
                position: parseNumber(position),
                candidates: parseNumber(candidates),
                breakdown: showBreakdown ? breakdown : [],
            }),
        });
        setSaving(false);

        if (!response.ok) {
            const error: { message?: string } = await response
                .json()
                .catch(() => ({}));
            notifyError(error.message ?? 'Não deu pra salvar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onChange(data.contest);
    }

    async function remove() {
        setSaving(true);
        const response = await csrfFetch(`/contests/${contest.id}/result`, {
            method: 'DELETE',
        });
        setSaving(false);

        if (!response.ok) {
            notifyError('Não deu pra apagar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onChange(data.contest);
    }

    return (
        <div className="space-y-2">
            <p className="rm-eyebrow">resultado</p>

            <div className="grid grid-cols-3 gap-2">
                <label className="t-label text-muted-fg">
                    nota
                    <input
                        inputMode="decimal"
                        value={score}
                        onChange={(e) => setScore(e.target.value)}
                        placeholder="62"
                        className={INPUT}
                    />
                </label>
                <label className="t-label text-muted-fg">
                    máximo
                    <input
                        inputMode="decimal"
                        value={maxScore}
                        onChange={(e) => setMaxScore(e.target.value)}
                        placeholder="70"
                        className={INPUT}
                    />
                </label>
                <label className="t-label text-muted-fg">
                    corte
                    <input
                        inputMode="decimal"
                        value={cutoff}
                        onChange={(e) => setCutoff(e.target.value)}
                        placeholder="opcional"
                        className={INPUT}
                    />
                </label>
            </div>

            <p className="t-body tabular-nums" aria-live="polite">
                {preview === null ? (
                    <span className="text-muted-fg">
                        Preenche nota e máximo.
                    </span>
                ) : (
                    <>
                        <strong>{pct(preview.percent)}</strong>
                        {preview.margin !== null && (
                            <span
                                className={
                                    preview.margin >= 0
                                        ? 'text-ink'
                                        : 'text-red-text'
                                }
                            >
                                {' · '}
                                {preview.margin >= 0 ? '+' : '−'}
                                {fmt(
                                    Math.round(Math.abs(preview.margin) * 100) /
                                        100,
                                )}{' '}
                                {preview.margin >= 0
                                    ? 'acima do corte'
                                    : 'abaixo do corte'}
                            </span>
                        )}
                    </>
                )}
            </p>

            <div className="grid grid-cols-2 gap-2">
                <label className="t-label text-muted-fg">
                    resultado
                    <select
                        value={outcome}
                        onChange={(e) =>
                            setOutcome(e.target.value as ExamOutcomeValue)
                        }
                        className={INPUT}
                    >
                        {OUTCOMES.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="t-label text-muted-fg">
                    origem da nota
                    <select
                        value={source}
                        onChange={(e) =>
                            setSource(e.target.value as ResultSourceValue)
                        }
                        className={INPUT}
                    >
                        {SOURCES.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="t-label text-muted-fg">
                    classificação
                    <input
                        inputMode="numeric"
                        value={position}
                        onChange={(e) => setPosition(e.target.value)}
                        placeholder="opcional"
                        className={INPUT}
                    />
                </label>
                <label className="t-label text-muted-fg">
                    candidatos
                    <input
                        inputMode="numeric"
                        value={candidates}
                        onChange={(e) => setCandidates(e.target.value)}
                        placeholder="opcional"
                        className={INPUT}
                    />
                </label>
            </div>

            {!showBreakdown ? (
                <button
                    type="button"
                    onClick={() => {
                        setShowBreakdown(true);

                        if (rows.length === 0) {
                            setRows([{ subject: '', score: '', max: '' }]);
                        }
                    }}
                    className="t-body inline-flex min-h-7 items-center underline underline-offset-2"
                >
                    + detalhar por matéria
                </button>
            ) : (
                <fieldset className="space-y-1.5 border-t border-line pt-2">
                    <legend className="t-label text-muted-fg">
                        por matéria (opcional)
                    </legend>
                    <datalist id={listId}>
                        {subjects.map((s) => (
                            <option key={s} value={s} />
                        ))}
                    </datalist>
                    {rows.map((row, i) => (
                        <div
                            key={i}
                            className="grid grid-cols-[1fr_4.5rem_4.5rem_auto] gap-1.5"
                        >
                            <input
                                list={listId}
                                aria-label={`matéria ${i + 1}`}
                                value={row.subject}
                                onChange={(e) =>
                                    updateRow(i, { subject: e.target.value })
                                }
                                placeholder="matéria"
                                className={INPUT}
                            />
                            <input
                                inputMode="decimal"
                                aria-label={`nota em matéria ${i + 1}`}
                                value={row.score}
                                onChange={(e) =>
                                    updateRow(i, { score: e.target.value })
                                }
                                placeholder="nota"
                                className={INPUT}
                            />
                            <input
                                inputMode="decimal"
                                aria-label={`máximo em matéria ${i + 1}`}
                                value={row.max}
                                onChange={(e) =>
                                    updateRow(i, { max: e.target.value })
                                }
                                placeholder="máx."
                                className={INPUT}
                            />
                            <button
                                type="button"
                                aria-label={`remover matéria ${i + 1}`}
                                onClick={() =>
                                    setRows((prev) =>
                                        prev.filter((_, j) => j !== i),
                                    )
                                }
                                className="t-body min-h-7 px-1.5 text-muted-fg"
                            >
                                ×
                            </button>
                        </div>
                    ))}
                    <button
                        type="button"
                        onClick={() =>
                            setRows((prev) => [
                                ...prev,
                                { subject: '', score: '', max: '' },
                            ])
                        }
                        className="t-body inline-flex min-h-7 items-center underline underline-offset-2"
                    >
                        + matéria
                    </button>
                    {sumMismatch && (
                        <p className="t-body text-muted-fg">
                            As matérias somam {fmt(rowsTotal)}, a nota é{' '}
                            {fmt(scoreN)}. Tudo bem se a prova tem peso.
                        </p>
                    )}
                </fieldset>
            )}

            <div className="flex items-center gap-3 pt-1">
                <button
                    type="button"
                    onClick={save}
                    disabled={saving || preview === null}
                    className="t-body min-h-7 border border-line-strong px-3 py-1.5 font-semibold disabled:cursor-not-allowed disabled:text-muted-fg"
                >
                    {saving ? 'salvando…' : 'salvar resultado'}
                </button>
                {result !== null && (
                    <button
                        type="button"
                        onClick={remove}
                        disabled={saving}
                        className="t-body inline-flex min-h-7 items-center text-red-text underline underline-offset-2"
                    >
                        apagar resultado
                    </button>
                )}
            </div>
        </div>
    );
}
