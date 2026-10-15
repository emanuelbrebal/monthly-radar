/**
 * Espelha os enums PHP de app/Enums (docs/03-ui-inventario.md, rodapé).
 * Manter em sincronia manualmente — não há geração automática no MVP.
 */

export type TiAreaValue = 'development' | 'data' | 'infrastructure' | 'security' | 'support' | 'it_governance' | 'all';

export type EditalStatusValue =
    | 'expected'
    | 'authorized'
    | 'in_preparation'
    | 'published'
    | 'registration_open'
    | 'registration_closed'
    | 'exam_done'
    | 'finished'
    | 'suspended';

export type EnrollmentStatusValue = 'not_enrolled' | 'interested' | 'enrolled' | 'fee_paid' | 'venue_released' | 'exam_taken' | 'dismissed';

export type EventTypeValue = 'registration_start' | 'registration_end' | 'fee_deadline' | 'exam' | 'result' | 'other';

export type RegimeValue = 'clt' | 'statutory' | 'temporary' | 'unknown';

export type EnrichmentStatusValue = 'pending' | 'skipped' | 'done' | 'failed';

export type RunStatusValue = 'queued' | 'scraping' | 'enriching' | 'done' | 'failed';

export type RegionValue = 'north' | 'northeast' | 'midwest' | 'southeast' | 'south';

export type ContestTierValue = 'elite' | 'alto' | 'intermediario' | 'entrada';

/**
 * Espelha `ContestTier` (label + `order()`): do topo da escada para a base.
 * `color` é a classe de fundo do token do handoff usada na marca do nível.
 */
export const CONTEST_TIERS: Array<{ value: ContestTierValue; label: string; short: string; color: string }> = [
    { value: 'elite', label: 'elite', short: 'elite', color: 'bg-red' },
    { value: 'alto', label: 'alto', short: 'alto', color: 'bg-gold' },
    { value: 'intermediario', label: 'intermediário', short: 'inter.', color: 'bg-blue' },
    { value: 'entrada', label: 'entrada', short: 'entrada', color: 'bg-green' },
];

/** Badge visual (docs/03-ui-inventario.md → tabela de status/evento). */
export type BadgeKind = 'pos' | 'neu' | 'neg' | 'info';

/** Checklist sequencial — espelha a ordem de `EnrollmentStatus::order()` (Dismissed fica fora). */
export const ENROLLMENT_STEPS: Array<{ value: EnrollmentStatusValue; label: string }> = [
    { value: 'not_enrolled', label: 'não inscrito' },
    { value: 'interested', label: 'quero fazer' },
    { value: 'enrolled', label: 'inscrito' },
    { value: 'fee_paid', label: 'taxa paga' },
    { value: 'venue_released', label: 'local de prova saiu' },
    { value: 'exam_taken', label: 'prova feita' },
];

export const EDITAL_STATUS_BADGE: Record<EditalStatusValue, BadgeKind> = {
    expected: 'info',
    authorized: 'neu',
    in_preparation: 'neu',
    published: 'neu',
    registration_open: 'pos',
    registration_closed: 'neg',
    exam_done: 'neg',
    finished: 'neg',
    suspended: 'neg',
};

export const EVENT_TYPE_BADGE: Record<EventTypeValue, BadgeKind> = {
    registration_start: 'pos',
    registration_end: 'neu',
    fee_deadline: 'neu',
    exam: 'neg',
    result: 'info',
    other: 'info',
};

export type ContestEventDto = {
    id: number;
    type: EventTypeValue;
    type_label: string;
    date: string;
    label: string | null;
};

/** Espelha `field_sources[campo]` gravado por `ContestMerger::recordFieldSource()`. */
export type FieldSourceDto = {
    url: string | null;
    fetched_at: string | null;
    origin: string;
    confidence: string;
};

export type ContestDto = {
    id: number;
    slug: string;
    organ: string;
    organ_acronym: string | null;
    year: number;
    title: string;
    board: string | null;
    areas: TiAreaValue[];
    status: EditalStatusValue;
    effective_status: EditalStatusValue;
    effective_status_label: string;
    regime: RegimeValue;
    states: string[];
    nationwide: boolean;
    registration_start: string | null;
    registration_end: string | null;
    exam_date: string | null;
    fee_cents: number | null;
    salary_min_cents: number | null;
    salary_max_cents: number | null;
    it_vacancies: number | null;
    tier: ContestTierValue | null;
    tier_label: string | null;
    tier_note: string | null;
    field_sources: Record<string, FieldSourceDto>;
    edital_url: string | null;
    registration_url: string | null;
    enrichment_status: EnrichmentStatusValue;
    enrollment_status: EnrollmentStatusValue;
    dismissed_at: string | null;
    dismissed_reason: string | null;
    pinned_slot: number | null;
    hides_in_hours: number | null;
    events: ContestEventDto[];
};

export type SourceErrorDto = { source: string; message: string };

export type SearchRunDto = {
    id: number;
    area: TiAreaValue;
    status: RunStatusValue;
    started_at: string;
    finished_at: string | null;
    counts: Record<string, number>;
    meta: { errors?: SourceErrorDto[] } & Record<string, unknown>;
};

export type AreaOption = { value: TiAreaValue; label: string };

export type StateOption = { value: string; label: string };

export type RegionOption = { value: RegionValue; label: string; states: StateOption[] };
