import { Head } from '@inertiajs/react';

/**
 * Placeholder mínimo da tela principal (T0). A maleta, o calendário e a caixa
 * de itens de verdade entram na T4/T5 — ver docs/03-ui-inventario.md e
 * docs/04-plano-execucao.md.
 */
export default function RadarIndex() {
    return (
        <>
            <Head title="Radar Mensal" />
            <div className="flex h-full flex-1 items-center justify-center p-8">
                <p className="text-lg">Radar Mensal · Concursos — em construção</p>
            </div>
        </>
    );
}
