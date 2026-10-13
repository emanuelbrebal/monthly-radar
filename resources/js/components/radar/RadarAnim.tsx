/**
 * Radar animado (varrendo) — portado de
 * design/radar mensal/design_handoff_radar_mensal/shared.jsx.
 */
export function RadarAnim({ size = 18 }: { size?: number }) {
    return (
        <div style={{ position: 'relative', width: size, height: size }}>
            <svg width={size} height={size} viewBox="0 0 100 100">
                <defs>
                    <radialGradient id="rm-radar-anim-gradient" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stopColor="#4a7c3d" stopOpacity="0" />
                        <stop offset="100%" stopColor="#4a7c3d" stopOpacity="0.18" />
                    </radialGradient>
                </defs>
                <circle cx="50" cy="50" r="48" fill="url(#rm-radar-anim-gradient)" stroke="#1a1612" strokeWidth="0.8" />
                <circle cx="50" cy="50" r="32" fill="none" stroke="#1a1612" strokeWidth="0.4" opacity="0.4" />
                <circle cx="50" cy="50" r="16" fill="none" stroke="#1a1612" strokeWidth="0.4" opacity="0.4" />
                <line x1="50" y1="2" x2="50" y2="98" stroke="#1a1612" strokeWidth="0.4" opacity="0.3" />
                <line x1="2" y1="50" x2="98" y2="50" stroke="#1a1612" strokeWidth="0.4" opacity="0.3" />
                <g style={{ transformOrigin: '50px 50px', animation: 'rm-radar-sweep 4s linear infinite' }}>
                    <path d="M50 50 L50 2 A48 48 0 0 1 95.5 35 Z" fill="#4a7c3d" opacity="0.18" />
                    <line x1="50" y1="50" x2="50" y2="2" stroke="#4a7c3d" strokeWidth="1" />
                </g>
                <circle cx="68" cy="30" r="2" fill="#c8553d" />
                <circle cx="34" cy="62" r="1.4" fill="#1a1612" />
                <circle cx="72" cy="58" r="1.4" fill="#1a1612" />
            </svg>
            <style>{`@keyframes rm-radar-sweep { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>
        </div>
    );
}
