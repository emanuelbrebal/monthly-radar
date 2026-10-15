import type { BadgeKind } from '@/types/radar';

type TagProps = {
    label: string;
    kind: BadgeKind;
    icon?: string;
    className?: string;
};

/** Badge de status/evento (12px/600, texto + cor) — docs/03-ui-inventario.md → tabelas de status/evento. */
export function Tag({ label, kind, icon, className = '' }: TagProps) {
    return (
        <span className={`rm-badge ${kind} ${className}`}>
            {icon && <span aria-hidden="true">{icon}</span>}
            <span>{label}</span>
        </span>
    );
}
