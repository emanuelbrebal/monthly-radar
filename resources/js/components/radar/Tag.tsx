import type { BadgeKind } from '@/types/radar';

type TagProps = {
    label: string;
    kind: BadgeKind;
    icon?: string;
};

/** Badge mono uppercase — docs/03-ui-inventario.md → tabelas de status/evento. */
export function Tag({ label, kind, icon }: TagProps) {
    return (
        <span className={`rm-badge ${kind}`}>
            {icon && <span className="opacity-70">{icon}</span>}
            <span>{label}</span>
        </span>
    );
}
