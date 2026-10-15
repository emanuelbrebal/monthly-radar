import { Tag } from '@/components/radar/Tag';
import { cn } from '@/lib/utils';
import { TONE_ICON } from '@/types/radar';
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
            className={cn(wrap && 'leading-tight whitespace-normal', className)}
        />
    );
}

/** Marca de concurso recém-inserido ainda sem ação do usuário (`is_new`). */
export function NewTag({ className = '' }: { className?: string }) {
    return (
        <Tag
            label="novo"
            kind="info"
            className={cn('font-bold uppercase', className)}
        />
    );
}
