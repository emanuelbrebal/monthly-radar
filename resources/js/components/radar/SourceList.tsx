import { Tag } from '@/components/radar/Tag';
import type { BadgeKind, FieldSourceDto } from '@/types/radar';

type SourceListProps = {
    fieldSources: Record<string, FieldSourceDto>;
};

const CONFIDENCE_BADGE: Record<string, BadgeKind> = {
    high: 'pos',
    medium: 'neu',
    low: 'neg',
    unconfirmed: 'info',
};

const FIELD_LABELS: Record<string, string> = {
    board: 'banca',
    registration_start: 'início das inscrições',
    registration_end: 'fim das inscrições',
    exam_date: 'data da prova',
    fee_cents: 'taxa',
    salary_min_cents: 'salário mínimo',
    salary_max_cents: 'salário máximo',
    it_vacancies: 'vagas de TI',
    regime: 'regime',
    edital_url: 'edital',
    registration_url: 'inscrição',
    states: 'estados',
};

function domain(url: string | null): string {
    if (!url) {
        return '—';
    }

    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

/** docs/03-ui-inventario.md → "Modal do dossiê → Fontes". Todo campo exibido tem origem em `field_sources` (CLAUDE.md regra 7). */
export function SourceList({ fieldSources }: SourceListProps) {
    const entries = Object.entries(fieldSources ?? {});

    if (entries.length === 0) {
        return <p className="t-body text-muted-fg">Nenhuma fonte registrada ainda.</p>;
    }

    return (
        <ul className="space-y-2">
            {entries.map(([field, source]) => (
                <li key={field} className="t-body flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="t-label w-36 shrink-0 truncate text-muted-fg">{FIELD_LABELS[field] ?? field}</span>
                    {source.url ? (
                        <a href={source.url} target="_blank" rel="noreferrer" className="inline-flex min-h-6 items-center truncate underline underline-offset-2 hover:text-red-text">
                            {domain(source.url)}
                        </a>
                    ) : (
                        <span className="truncate text-muted-fg">{source.origin}</span>
                    )}
                    <span className="t-label shrink-0 text-muted-fg">
                        {source.fetched_at ? new Date(source.fetched_at).toLocaleDateString('pt-BR') : '—'}
                    </span>
                    <Tag label={source.confidence} kind={CONFIDENCE_BADGE[source.confidence] ?? 'info'} />
                </li>
            ))}
        </ul>
    );
}
