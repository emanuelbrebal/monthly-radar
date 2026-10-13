type RadarMarkProps = {
    size?: number;
    color?: string;
    accent?: string;
};

/** Logomarca radar mensal — portado de design/radar mensal/design_handoff_radar_mensal/shared.jsx. */
export function RadarMark({ size = 32, color = 'var(--ink)', accent = 'var(--red)' }: RadarMarkProps) {
    return (
        <svg width={size} height={size} viewBox="0 0 56 56" className="block">
            <circle cx="28" cy="28" r="26" fill="none" stroke={color} strokeWidth="1.5" />
            <circle cx="28" cy="28" r="18" fill="none" stroke={color} strokeWidth="1" opacity="0.5" />
            <circle cx="28" cy="28" r="10" fill="none" stroke={color} strokeWidth="1" opacity="0.3" />
            <line x1="28" y1="2" x2="28" y2="54" stroke={color} strokeWidth="0.8" opacity="0.3" />
            <line x1="2" y1="28" x2="54" y2="28" stroke={color} strokeWidth="0.8" opacity="0.3" />
            <path d="M28 28 L48 14" stroke={accent} strokeWidth="2" strokeLinecap="round" />
            <circle cx="48" cy="14" r="3" fill={accent} />
            <circle cx="14" cy="40" r="1.6" fill={color} />
            <circle cx="38" cy="36" r="1.6" fill={color} />
            <circle cx="20" cy="18" r="1.6" fill={color} />
        </svg>
    );
}
