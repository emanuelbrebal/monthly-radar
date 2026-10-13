import type { ReactNode } from 'react';

type StampProps = {
    children: ReactNode;
    rotate?: number;
    className?: string;
};

/** Carimbo de tinta vermelha — docs/03-ui-inventario.md ("some em Xh", etc). */
export function Stamp({ children, rotate = -8, className = '' }: StampProps) {
    return (
        <span className={`rm-stamp ${className}`} style={{ transform: `rotate(${rotate}deg)` }}>
            {children}
        </span>
    );
}
