import { Circle, Diamond, Square, Triangle } from 'lucide-react';
import { CONTEST_TIERS } from '@/types/radar';
import type { ContestTierValue, TierIcon } from '@/types/radar';

type TierMarkProps = {
    tier: ContestTierValue | null;
    /**
     * `dot` = só o ícone (texto em sr-only); `chip` = ícone + texto num selo com fundo e borda;
     * `label` = ícone + texto sem fundo, herdando a cor do texto (compatibilidade).
     */
    variant?: 'dot' | 'chip' | 'label';
    /** Superfície onde a marca aparece: define o par de cores AA. */
    on?: 'paper' | 'night';
    className?: string;
};

const ICONS: Record<TierIcon, typeof Circle> = {
    diamond: Diamond,
    square: Square,
    triangle: Triangle,
    circle: Circle,
};

export function tierMeta(tier: ContestTierValue | null) {
    return CONTEST_TIERS.find((t) => t.value === tier) ?? null;
}

/** Marca do nível do concurso (curadoria manual `tier`): forma + texto + cor, nunca só cor. */
export function TierMark({ tier, variant = 'dot', on = 'paper', className = '' }: TierMarkProps) {
    const meta = tierMeta(tier);

    if (meta === null) {
        return null;
    }

    const Icon = ICONS[meta.icon];
    const night = on === 'night';
    const iconColor = night ? meta.onNightClass : meta.lineClass;
    const icon = <Icon aria-hidden="true" className={`h-3 w-3 shrink-0 ${variant === 'chip' && !night ? meta.fgClass : iconColor}`} fill="currentColor" strokeWidth={2} />;

    if (variant === 'chip') {
        const chip = night ? `border ${meta.onNightClass} ${meta.onNightBorderClass}` : `border ${meta.chipClass}`;

        return (
            <span className={`t-label inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 whitespace-nowrap ${chip} ${className}`} title={`nível: ${meta.label}`}>
                {icon}
                {meta.label}
            </span>
        );
    }

    return (
        <span className={`inline-flex items-center gap-1 ${className}`} title={`nível: ${meta.label}`}>
            {icon}
            {variant === 'label' ? <span className="t-label">{meta.label}</span> : <span className="sr-only">nível: {meta.label}</span>}
        </span>
    );
}
