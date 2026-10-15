import { Tag } from '@/components/radar/Tag';
import { TONE_ICON } from '@/lib/radar';
import type { ContestDto } from '@/types/radar';

type PhaseTagProps = {
    contest: ContestDto;
    /** `wrap` deixa o rótulo quebrar em 2 linhas (slot estreito da maleta). */
    wrap?: boolean;
    className?: string;
};

/** Fase atual do concurso (derivada no backend por ContestPhaseResolver), com forma + texto + cor. */
export function PhaseTag({
    contest,
    wrap = false,
    className = '',
}: PhaseTagProps) {
    return (
        <Tag
            label={contest.phase_label}
            kind={contest.phase_tone}
            icon={TONE_ICON[contest.phase_tone]}
            className={`${wrap ? 'leading-tight whitespace-normal' : ''} ${className}`}
        />
    );
}

/** Marca de concurso recém-inserido ainda sem ação do usuário (`is_new`). */
export function NewTag({ className = '' }: { className?: string }) {
    return (
        <span className={`rm-badge info font-bold uppercase ${className}`}>
            novo
        </span>
    );
}
