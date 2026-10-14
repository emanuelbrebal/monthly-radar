import { CONTEST_TIERS } from '@/types/radar';
import type { ContestTierValue } from '@/types/radar';

type TierMarkProps = {
    tier: ContestTierValue | null;
    /** `dot` = quadradinho de cor; `label` = quadradinho + nome do nível. */
    variant?: 'dot' | 'label';
    className?: string;
};

export function tierMeta(tier: ContestTierValue | null) {
    return CONTEST_TIERS.find((t) => t.value === tier) ?? null;
}

/** Marca do nível do concurso (curadoria manual `tier`) — cor por degrau da escada. */
export function TierMark({ tier, variant = 'dot', className = '' }: TierMarkProps) {
    const meta = tierMeta(tier);

    if (meta === null) {
        return null;
    }

    return (
        <span className={`inline-flex items-center gap-1 ${className}`} title={`nível: ${meta.label}`}>
            <span className={`inline-block h-2 w-2 shrink-0 ${meta.color}`} />
            {variant === 'label' && <span className="rm-mono text-[10px] uppercase">{meta.label}</span>}
        </span>
    );
}
