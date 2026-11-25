import { Head, Link, usePage } from '@inertiajs/react';

type TimelineItem = {
    date: string;
    days_left: number;
    contest: string;
    event: string;
    type: string;
};

type WallContest = {
    id: number;
    name: string;
    title: string;
    board: string | null;
    exam_date: string | null;
    exam_city: string | null;
    phase_label: string;
    status_label: string;
    has_upcoming: boolean;
};

type WallSheetProps = {
    generatedAt: string;
    timeline: TimelineItem[];
    contests: WallContest[];
};

/** Fases para marcar à caneta — a ordem é a da vida real de um concurso. */
const PHASES = [
    'edital previsto',
    'edital publicado',
    'inscrito',
    'taxa paga',
    'local de prova',
    'prova feita',
    'gabarito / recurso',
    'resultado',
    'nomeação',
];

const WEEKDAYS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

/** 'AAAA-MM-DD' → partes, sem passar por UTC (evita cair no dia anterior). */
function parts(iso: string) {
    const [y, m, d] = iso.split('-').map(Number);

    return { y, m, d, weekday: WEEKDAYS[new Date(y, m - 1, d).getDay()] };
}

function ddmm(iso: string) {
    const { m, d } = parts(iso);

    return `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}`;
}

function ddmmyyyy(iso: string) {
    return `${ddmm(iso)}/${parts(iso).y}`;
}

function daysLeft(n: number) {
    if (n === 0) {
        return 'hoje';
    }

    return n === 1 ? 'amanhã' : `${n} d`;
}

function Box() {
    return (
        <span
            aria-hidden="true"
            className="inline-block size-[5mm] shrink-0 border-[1.5px] border-ink bg-white"
        />
    );
}

function SectionTitle({ children }: { children: string }) {
    return (
        <h2 className="mb-1.5 border-b-2 border-ink pb-0.5 font-mono text-[9pt] font-semibold tracking-wider uppercase">
            {children}
        </h2>
    );
}

/**
 * Folha da parede (tasks/wall-sheet.md): A4 retrato, P&B, para imprimir e
 * marcar à caneta. Nada aqui depende de cor — fase e "já passou" são marcados
 * à mão nos quadrados. Na tela, só a barra de cima é extra (some no print).
 */
export default function WallSheet() {
    const { generatedAt, timeline, contests } = usePage<WallSheetProps>().props;
    const undated = contests.filter((c) => !c.has_upcoming);

    return (
        <>
            <Head title="Folha da parede · Radar Mensal" />

            <div className="min-h-screen bg-paper-deep print:min-h-0 print:bg-white">
                <div className="sticky top-0 z-10 flex items-center gap-4 border-b border-line-strong bg-paper px-6 py-3 print:hidden">
                    <Link
                        href="/"
                        className="t-body text-muted-fg underline-offset-4 hover:text-ink hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        ← voltar à mesa
                    </Link>
                    <p className="t-body text-muted-fg">
                        A4 retrato · margens padrão · sem cabeçalhos do
                        navegador
                    </p>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="t-body ml-auto border border-line-strong bg-red-text px-4 py-1.5 font-semibold text-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        imprimir
                    </button>
                </div>

                <article
                    aria-label="Folha para imprimir"
                    className="wall-sheet mx-auto my-6 flex min-h-[297mm] w-[210mm] flex-col bg-white p-[12mm] text-[9.5pt] leading-snug text-black shadow-md print:m-0 print:min-h-[273mm] print:w-auto print:p-0 print:shadow-none"
                >
                    <header className="mb-4 flex items-end justify-between border-b-[3px] border-double border-ink pb-1.5">
                        <h1 className="font-display text-[20pt] leading-none">
                            radar mensal · quadro da parede
                        </h1>
                        <p className="font-mono text-[8pt]">
                            atualizado em {ddmmyyyy(generatedAt)}
                        </p>
                    </header>

                    <section aria-label="Próximas datas" className="mb-4">
                        <SectionTitle>próximas datas</SectionTitle>
                        {timeline.length === 0 ? (
                            <p className="italic">
                                Nenhuma data futura nos concursos acompanhados.
                            </p>
                        ) : (
                            <table className="w-full border-collapse">
                                <thead>
                                    <tr className="text-left font-mono text-[7.5pt] uppercase">
                                        <th className="w-[8mm] pb-1 font-normal">
                                            <span className="sr-only">
                                                passou
                                            </span>
                                        </th>
                                        <th className="w-[16mm] pb-1 font-normal">
                                            data
                                        </th>
                                        <th className="w-[11mm] pb-1 font-normal">
                                            dia
                                        </th>
                                        <th className="w-[17mm] pb-1 font-normal">
                                            faltam
                                        </th>
                                        <th className="w-[38mm] pb-1 font-normal">
                                            concurso
                                        </th>
                                        <th className="pb-1 font-normal">
                                            evento
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {timeline.map((item, i) => (
                                        <tr
                                            key={`${item.date}-${item.contest}-${item.event}-${i}`}
                                            className={`break-inside-avoid border-t border-black/25 ${
                                                item.type === 'exam'
                                                    ? 'font-semibold'
                                                    : ''
                                            }`}
                                        >
                                            <td className="py-[1.2mm]">
                                                <Box />
                                            </td>
                                            <td className="font-mono">
                                                {ddmm(item.date)}
                                            </td>
                                            <td className="font-mono">
                                                {parts(item.date).weekday}
                                            </td>
                                            <td className="font-mono">
                                                {daysLeft(item.days_left)}
                                            </td>
                                            <td>{item.contest}</td>
                                            <td>{item.event}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>

                    <section
                        aria-label="Fase de cada concurso"
                        className="mb-4"
                    >
                        <SectionTitle>fase de cada concurso</SectionTitle>
                        {contests.length === 0 ? (
                            <p className="italic">
                                Nenhum concurso na maleta nem marcado como
                                “quero fazer” ou inscrito.
                            </p>
                        ) : (
                            <table className="w-full table-fixed border-collapse">
                                <thead>
                                    <tr className="align-bottom font-mono text-[6.5pt] leading-tight uppercase">
                                        <th className="w-[58mm] pb-1 text-left font-normal">
                                            concurso
                                        </th>
                                        {PHASES.map((phase) => (
                                            <th
                                                key={phase}
                                                className="px-0.5 pb-1 text-center font-normal"
                                            >
                                                {phase}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {contests.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="break-inside-avoid border-t border-black/25"
                                        >
                                            <td className="py-[1.5mm] pr-2 align-top">
                                                <p className="font-semibold">
                                                    {c.name}
                                                    {c.board
                                                        ? ` · ${c.board}`
                                                        : ''}
                                                </p>
                                                <p className="text-[7.5pt]">
                                                    {c.exam_date
                                                        ? `prova ${ddmmyyyy(c.exam_date)}`
                                                        : 'prova sem data'}
                                                    {c.exam_city
                                                        ? ` · ${c.exam_city}`
                                                        : ''}
                                                    {` · hoje: ${c.phase_label}`}
                                                </p>
                                            </td>
                                            {PHASES.map((phase) => (
                                                <td
                                                    key={phase}
                                                    className="text-center align-middle"
                                                >
                                                    <Box />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>

                    {undated.length > 0 && (
                        <section aria-label="Sem data à vista" className="mb-4">
                            <SectionTitle>sem data à vista</SectionTitle>
                            <ul>
                                {undated.map((c) => (
                                    <li
                                        key={c.id}
                                        className="flex break-inside-avoid items-baseline gap-2 border-t border-black/25 py-[1.2mm]"
                                    >
                                        <Box />
                                        <span className="font-semibold">
                                            {c.name}
                                        </span>
                                        <span className="text-[8pt]">
                                            {c.status_label}
                                            {c.board ? ` · ${c.board}` : ''}
                                            {' · previsão: '}
                                            <span className="inline-block w-[30mm] border-b border-black/50" />
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <section
                        aria-label="Anotações"
                        className="flex min-h-[25mm] flex-1 flex-col"
                    >
                        <SectionTitle>anotações</SectionTitle>
                        <div className="wall-sheet-lines flex-1" />
                    </section>
                </article>
            </div>
        </>
    );
}
