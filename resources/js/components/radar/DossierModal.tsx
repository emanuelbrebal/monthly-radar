import { useState } from 'react';

import { EnrollmentChecklist } from '@/components/radar/EnrollmentChecklist';
import { SourceList } from '@/components/radar/SourceList';
import { Stamp } from '@/components/radar/Stamp';
import { Tag } from '@/components/radar/Tag';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import { EDITAL_STATUS_BADGE } from '@/types/radar';
import type { ContestDto } from '@/types/radar';

type DossierModalProps = {
    contest: ContestDto | null;
    onOpenChange: (open: boolean) => void;
    onContestChange: (contest: ContestDto) => void;
    onDismissed: (contest: ContestDto) => void;
};

function money(cents: number | null): string | null {
    if (cents === null) {
        return null;
    }

    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function dateLabel(date: string): string {
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year}`;
}

/**
 * docs/03-ui-inventario.md → "Modal do dossiê". Fica dentro do escopo de
 * dados que o app realmente tem hoje (sem "resumo"/"cargos de TI"/
 * "divergências" — o schema do dossiê em docs/agents/dossie.md não gera
 * esse texto narrativo, só campos + fonte).
 */
export function DossierModal({ contest, onOpenChange, onContestChange, onDismissed }: DossierModalProps) {
    const [pinning, setPinning] = useState(false);
    const [dismissing, setDismissing] = useState(false);
    const [reasonOpen, setReasonOpen] = useState(false);
    const [reason, setReason] = useState('');

    if (contest === null) {
        return null;
    }

    const pinned = contest.pinned_slot !== null;
    const dismissed = contest.enrollment_status === 'dismissed';

    async function togglePin() {
        if (contest === null) {
            return;
        }

        setPinning(true);
        const response = await csrfFetch(`/contests/${contest.id}/pin`, { method: pinned ? 'DELETE' : 'POST' });
        setPinning(false);

        if (!response.ok) {
            const error: { message?: string } = await response.json().catch(() => ({}));
            notifyError(error.message ?? 'Não deu pra salvar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onContestChange(data.contest);
    }

    async function confirmDismiss() {
        if (contest === null) {
            return;
        }

        setDismissing(true);
        const response = await csrfFetch(`/contests/${contest.id}/dismiss`, {
            method: 'POST',
            body: JSON.stringify({ reason: reason.trim() || null }),
        });
        setDismissing(false);

        if (!response.ok) {
            const error: { message?: string } = await response.json().catch(() => ({}));
            notifyError(error.message ?? 'Não deu pra descartar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        setReasonOpen(false);
        setReason('');
        onOpenChange(false);
        onDismissed(data.contest);
    }

    async function restore() {
        if (contest === null) {
            return;
        }

        const response = await csrfFetch(`/contests/${contest.id}/restore`, { method: 'POST' });

        if (!response.ok) {
            notifyError('Não deu pra trazer de volta. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onContestChange(data.contest);
    }

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="grid max-h-[88vh] w-[min(1040px,94vw)] max-w-none grid-cols-1 gap-6 overflow-y-auto border-ink bg-card p-0 sm:max-w-none sm:grid-cols-[1.3fr_1fr]">
                <DialogTitle className="sr-only">
                    dossiê · {contest.organ_acronym ?? contest.organ} · {contest.year}
                </DialogTitle>
                <DialogDescription className="sr-only">
                    Detalhes do concurso {contest.title}: linha do tempo, fontes, minha situação e opções de fixar ou descartar.
                </DialogDescription>

                <div className="col-span-full flex items-center justify-between border-b border-ink px-6 pt-6 pb-3">
                    <span className="rm-mono text-[11px] uppercase text-gold">dossiê · {contest.organ_acronym ?? contest.organ} · {contest.year}</span>
                    <Tag label={dismissed ? 'DESCARTADO' : contest.effective_status_label} kind={dismissed ? 'neu' : EDITAL_STATUS_BADGE[contest.effective_status]} />
                </div>

                {contest.enrichment_status === 'failed' || contest.enrichment_status === 'skipped' ? (
                    <div className="col-span-full -mt-3 mx-6 rounded-sm border border-gold bg-gold/10 px-3 py-1.5 text-xs">
                        Dossiê incompleto — só dados raspados.
                    </div>
                ) : null}

                <div className="space-y-4 px-6 pb-6">
                    <div>
                        <p className="rm-mono text-[11px] uppercase text-red">
                            {contest.organ} · {contest.board ?? 'banca não definida'} · {contest.regime}
                        </p>
                        <h2 className="rm-display mt-1 text-3xl">{contest.title}</h2>
                    </div>

                    {dismissed && contest.dismissed_reason && <p className="rm-serif text-sm italic text-ink-mid">{contest.dismissed_reason}</p>}

                    <div>
                        <p className="rm-eyebrow mb-1.5">linha do tempo</p>
                        {contest.events.length === 0 ? (
                            <p className="rm-serif text-xs text-ink-mid italic">Sem datas registradas ainda.</p>
                        ) : (
                            <ul className="space-y-1">
                                {contest.events.map((e) => {
                                    const past = e.date < new Date().toISOString().slice(0, 10);

                                    return (
                                        <li key={e.id} className={`flex items-center gap-2 text-xs ${past ? 'text-ink-mid line-through' : ''}`}>
                                            <span className="rm-mono w-20 shrink-0">{dateLabel(e.date)}</span>
                                            <span>{e.label ?? e.type_label}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    <div>
                        <p className="rm-eyebrow mb-1.5">fontes</p>
                        <SourceList fieldSources={contest.field_sources} />
                    </div>
                </div>

                <div className="space-y-4 border-t border-ink px-6 py-6 sm:border-t-0 sm:border-l">
                    <div className="grid grid-cols-2 gap-3">
                        <MiniStat label="salário" value={money(contest.salary_max_cents ?? contest.salary_min_cents)} />
                        <MiniStat label="vagas TI" value={contest.it_vacancies !== null ? String(contest.it_vacancies) : null} />
                        <MiniStat label="taxa" value={money(contest.fee_cents)} />
                        <MiniStat label="prova" value={contest.exam_date ? dateLabel(contest.exam_date) : null} />
                    </div>

                    <div className="space-y-1.5">
                        <a
                            href={contest.edital_url ?? undefined}
                            target="_blank"
                            rel="noreferrer"
                            aria-disabled={!contest.edital_url}
                            title={!contest.edital_url ? 'edital ainda não saiu' : undefined}
                            className={`block rounded-sm border border-ink bg-red-deep px-3 py-1.5 text-center text-xs font-semibold text-paper ${
                                !contest.edital_url ? 'pointer-events-none opacity-40' : ''
                            }`}
                        >
                            abrir edital ↗
                        </a>
                        {contest.registration_url && (
                            <a
                                href={contest.registration_url}
                                target="_blank"
                                rel="noreferrer"
                                className="block rounded-sm border border-ink px-3 py-1.5 text-center text-xs"
                            >
                                inscrição ↗
                            </a>
                        )}
                    </div>

                    <div>
                        <p className="rm-eyebrow mb-1.5">minha situação</p>
                        {dismissed ? (
                            <button type="button" onClick={restore} className="rm-mono text-xs underline">
                                trazer de volta
                            </button>
                        ) : (
                            <EnrollmentChecklist contest={contest} onChange={onContestChange} />
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={togglePin}
                        disabled={pinning || dismissed}
                        className="rm-mono w-full rounded-sm border border-ink px-3 py-1.5 text-xs uppercase disabled:opacity-40"
                    >
                        {pinned ? 'tirar da maleta' : 'guardar na maleta'}
                    </button>

                    {!dismissed && (
                        <div className="border-t border-dashed border-ink-soft/40 pt-3">
                            {!reasonOpen ? (
                                <button type="button" onClick={() => setReasonOpen(true)} className="rm-mono text-xs text-red underline">
                                    descartar este concurso
                                </button>
                            ) : (
                                <div className="space-y-2">
                                    <input
                                        type="text"
                                        maxLength={120}
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        placeholder="motivo, se quiser"
                                        className="rm-mono w-full rounded-sm border border-ink/30 px-2 py-1 text-xs"
                                    />
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={confirmDismiss}
                                            disabled={dismissing}
                                            className="rm-mono rounded-sm border border-red px-2 py-1 text-[11px] uppercase text-red disabled:opacity-50"
                                        >
                                            descartar
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setReasonOpen(false)}
                                            className="rm-mono rounded-sm border border-ink/30 px-2 py-1 text-[11px] uppercase"
                                        >
                                            cancelar
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {contest.hides_in_hours !== null && contest.hides_in_hours <= 24 && <Stamp rotate={-6}>some {contest.hides_in_hours}h</Stamp>}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function MiniStat({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <p className="rm-display text-lg">{value ?? '—'}</p>
            <p className="rm-mono text-[10px] uppercase text-ink-mid">{label}</p>
        </div>
    );
}
