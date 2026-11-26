import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { flushSync } from 'react-dom';

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
    /** Colunas de fase que o app já sabe cumpridas (0–6): saem preenchidas. */
    phase_done: number;
    has_upcoming: boolean;
    qr_svg: string | null;
    /** edital → inscrição → fonte salva (onde o edital deve aparecer). */
    qr_target: 'edital' | 'inscrição' | 'fonte' | null;
    qr_host: string | null;
};

type Orientation = 'portrait' | 'landscape';

/** QR inline (SVG do servidor). Para onde leva fica no texto ao lado. */
function QrCode({ contest, size }: { contest: WallContest; size: string }) {
    if (!contest.qr_svg || !contest.qr_target) {
        return (
            <span
                aria-hidden="true"
                className={`block shrink-0 border border-dashed border-black/40 ${size}`}
            />
        );
    }

    return (
        <span
            role="img"
            aria-label={`QR code: ${contest.qr_target} (${contest.qr_host ?? ''})`}
            className={`mx-auto block shrink-0 [&>svg]:size-full ${size}`}
            dangerouslySetInnerHTML={{ __html: contest.qr_svg }}
        />
    );
}

type WallSheetProps = {
    generatedAt: string;
    timeline: TimelineItem[];
    contests: WallContest[];
};

/**
 * Fases para marcar à caneta — a ordem é a da vida real de um concurso.
 * `short` vai no cabeçalho (coluna de ~12 mm); `label` fica no title.
 */
const PHASES = [
    { label: 'edital autorizado', short: 'autor.' },
    { label: 'edital publicado', short: 'ed. publ.' },
    { label: 'inscrito', short: 'inscr.' },
    { label: 'taxa paga', short: 'taxa paga' },
    { label: 'local de prova', short: 'local prova' },
    { label: 'prova feita', short: 'prova feita' },
    { label: 'gabarito / recurso', short: 'gab. rec.' },
    { label: 'resultado', short: 'result.' },
    { label: 'nomeação', short: 'nomea.' },
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

/** Quadrado de 5 mm. `filled` = o app já sabia quando imprimiu (preto chapado),
 * distinto de qualquer marca a lápis. */
function Box({ filled = false }: { filled?: boolean }) {
    return (
        <span
            aria-hidden="true"
            className={`inline-block size-[5mm] shrink-0 border-[1.5px] border-ink align-middle ${
                filled ? 'bg-black' : 'bg-white'
            }`}
        />
    );
}

/** Segunda-feira da semana de `iso` — separa semanas na lista de datas. */
function weekKey(iso: string) {
    const { y, m, d } = parts(iso);
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() - ((dt.getDay() + 6) % 7));

    return dt.toDateString();
}

const RULER_MAX_DAYS = 70;

/**
 * Régua: um quadrado por dia, do dia da impressão até a prova (borda grossa).
 * Risca-se um por manhã — a contagem nunca fica velha no papel. Dia do mês
 * dentro de cada quadrado, então pular um dia não desorienta.
 */
function DayRuler({ from, to }: { from: string; to: string }) {
    const a = parts(from);
    const b = parts(to);
    const end = new Date(b.y, b.m - 1, b.d);
    const days: { day: number; weekday: number }[] = [];

    for (
        const dt = new Date(a.y, a.m - 1, a.d);
        dt <= end && days.length <= RULER_MAX_DAYS;
        dt.setDate(dt.getDate() + 1)
    ) {
        days.push({ day: dt.getDate(), weekday: dt.getDay() });
    }

    if (days.length > RULER_MAX_DAYS) {
        return (
            <p className="font-mono text-[7.5pt]">
                a régua de dias aparece quando faltar até {RULER_MAX_DAYS} dias
            </p>
        );
    }

    return (
        <ol
            aria-label="Um quadrado por dia até a prova"
            className="flex flex-wrap gap-y-[1.2mm]"
        >
            {days.map((item, i) => {
                const isExam = i === days.length - 1;
                const gap =
                    i === 0
                        ? ''
                        : item.weekday === 1
                          ? 'ml-[2.2mm]'
                          : 'ml-[0.6mm]';

                return (
                    <li
                        key={i}
                        className={`flex size-[4.6mm] items-center justify-center border-black font-mono text-[6pt] leading-none ${gap} ${
                            isExam ? 'border-[2px] font-bold' : 'border'
                        }`}
                    >
                        {item.day}
                    </li>
                );
            })}
        </ol>
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
    // Sem data à vista já tem seção própria: não repete na tabela de fases.
    const staged = contests.filter((c) => c.has_upcoming);
    const nextExam = timeline.find((item) => item.type === 'exam') ?? null;
    const [orientation, setOrientation] = useState<Orientation>('portrait');
    const landscape = orientation === 'landscape';

    // Troca o layout de forma síncrona e só então abre o diálogo de impressão,
    // para o @page e as colunas já estarem na orientação escolhida.
    const printAs = (next: Orientation) => {
        flushSync(() => setOrientation(next));
        window.print();
    };

    return (
        <>
            <Head title="Folha da parede · Radar Mensal" />
            <style>{`@media print { @page { size: A4 ${orientation}; margin: 12mm; } }`}</style>

            <div className="min-h-screen bg-paper-deep print:min-h-0 print:bg-white">
                <div className="sticky top-0 z-10 flex items-center gap-4 border-b border-line-strong bg-paper px-6 py-3 print:hidden">
                    <Link
                        href="/"
                        className="t-body text-muted-fg underline-offset-4 hover:text-ink hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        ← voltar à mesa
                    </Link>
                    <p className="t-body text-muted-fg">
                        A4 {landscape ? 'paisagem' : 'retrato'} · margens padrão
                        · sem cabeçalhos do navegador
                    </p>
                    <div className="ml-auto flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => printAs('landscape')}
                            className="t-body border border-line-strong bg-card px-4 py-1.5 font-semibold text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            imprimir paisagem
                        </button>
                        <button
                            type="button"
                            onClick={() => printAs('portrait')}
                            className="t-body border border-line-strong bg-red-text px-4 py-1.5 font-semibold text-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            imprimir retrato
                        </button>
                    </div>
                </div>

                <article
                    aria-label="Folha para imprimir"
                    className={`wall-sheet mx-auto my-6 flex flex-col bg-white p-[12mm] text-[9.5pt] leading-snug text-black shadow-md print:m-0 print:w-auto print:p-0 print:shadow-none ${
                        landscape
                            ? 'min-h-[210mm] w-[297mm] print:min-h-[185mm]'
                            : 'min-h-[297mm] w-[210mm] print:min-h-[272mm]'
                    }`}
                >
                    <header className="mb-[2.5mm] flex items-end justify-between border-b-[3px] border-double border-ink pb-1.5">
                        <h1 className="font-display text-[20pt] leading-none">
                            radar mensal · quadro da parede
                        </h1>
                        <p className="font-mono text-[8pt]">
                            impresso em {ddmmyyyy(generatedAt)}
                        </p>
                    </header>

                    {/* Retrato: uma coluna ("contents" some com o wrapper).
                        Paisagem: datas à esquerda, fases/anotações à direita. */}
                    <div
                        className={
                            landscape
                                ? 'grid flex-1 grid-cols-[112mm_1fr] gap-x-[7mm]'
                                : 'flex flex-1 flex-col'
                        }
                    >
                        <div className={landscape ? 'min-w-0' : 'contents'}>
                            {nextExam && (
                                <section
                                    aria-label="Próxima prova"
                                    className="mb-[2.5mm] border-[2.5px] border-ink px-[5mm] py-[2mm]"
                                >
                                    <div className="mb-[1.5mm] flex items-end justify-between gap-4">
                                        <div>
                                            <p className="font-mono text-[8pt] tracking-wider uppercase">
                                                próxima prova
                                            </p>
                                            <p className="font-display text-[22pt] leading-tight">
                                                {nextExam.contest}
                                            </p>
                                        </div>
                                        <p className="text-right leading-none">
                                            <span className="font-display text-[28pt]">
                                                {ddmm(nextExam.date)}
                                            </span>
                                            <span className="block font-mono text-[8pt] uppercase">
                                                {parts(nextExam.date).weekday} ·{' '}
                                                {parts(nextExam.date).y}
                                            </span>
                                        </p>
                                    </div>
                                    <DayRuler
                                        from={generatedAt}
                                        to={nextExam.date}
                                    />
                                </section>
                            )}

                            <section
                                aria-label="Próximas datas"
                                className="mb-[2.5mm]"
                            >
                                <SectionTitle>próximas datas</SectionTitle>
                                {timeline.length === 0 ? (
                                    <p className="italic">
                                        Nenhuma data futura nos concursos
                                        acompanhados.
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
                                                <th
                                                    className={`${landscape ? 'w-[27mm]' : 'w-[38mm]'} pb-1 font-normal`}
                                                >
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
                                                    className={`break-inside-avoid border-t ${
                                                        i > 0 &&
                                                        weekKey(item.date) !==
                                                            weekKey(
                                                                timeline[i - 1]
                                                                    .date,
                                                            )
                                                            ? 'border-black'
                                                            : 'border-black/25'
                                                    } ${
                                                        item.type === 'exam'
                                                            ? 'font-semibold'
                                                            : ''
                                                    }`}
                                                >
                                                    <td className="py-[0.6mm]">
                                                        <Box />
                                                    </td>
                                                    <td className="font-mono">
                                                        {ddmm(item.date)}
                                                    </td>
                                                    <td className="font-mono">
                                                        {
                                                            parts(item.date)
                                                                .weekday
                                                        }
                                                    </td>
                                                    <td>{item.contest}</td>
                                                    <td>{item.event}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </section>
                        </div>

                        <div className="flex min-w-0 flex-1 flex-col">
                            <section
                                aria-label="Fase de cada concurso"
                                className="mb-[2.5mm]"
                            >
                                <SectionTitle>
                                    fase de cada concurso
                                </SectionTitle>
                                {staged.length === 0 ? (
                                    <p className="italic">
                                        {contests.length === 0
                                            ? 'Nenhum concurso na maleta nem marcado como “quero fazer” ou inscrito.'
                                            : 'Nenhum concurso com data à vista — veja abaixo.'}
                                    </p>
                                ) : (
                                    <table className="w-full table-fixed border-collapse">
                                        <thead>
                                            <tr
                                                className={`align-bottom font-mono leading-tight uppercase ${landscape ? 'text-[5.5pt]' : 'text-[6.5pt]'}`}
                                            >
                                                <th
                                                    className={`${landscape ? 'w-[46mm]' : 'w-[58mm]'} pb-1 text-left font-normal`}
                                                >
                                                    concurso
                                                </th>
                                                {PHASES.map((phase) => (
                                                    <th
                                                        key={phase.label}
                                                        title={phase.label}
                                                        className="px-0.5 pb-1 text-center font-normal"
                                                    >
                                                        {phase.short}
                                                    </th>
                                                ))}
                                                <th
                                                    className={`${landscape ? 'w-[13mm]' : 'w-[17mm]'} px-0.5 pb-1 text-center font-normal`}
                                                >
                                                    minha nota
                                                </th>
                                                <th
                                                    className={`${landscape ? 'w-[13mm]' : 'w-[15mm]'} pb-1 text-center font-normal`}
                                                >
                                                    qr
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {staged.map((c) => (
                                                <tr
                                                    key={c.id}
                                                    className="break-inside-avoid border-t border-black/25"
                                                >
                                                    <td className="py-[0.8mm] pr-2 align-top">
                                                        <p className="font-semibold">
                                                            {c.name}
                                                            {c.board
                                                                ? ` · ${c.board}`
                                                                : ''}
                                                        </p>
                                                        <p className="text-[7pt] leading-tight">
                                                            {c.exam_date
                                                                ? `prova ${ddmmyyyy(c.exam_date)}`
                                                                : 'prova sem data'}
                                                            {c.exam_city
                                                                ? ` · ${c.exam_city}`
                                                                : ''}
                                                            {' · conferido '}
                                                            <span className="inline-block w-[9mm] border-b border-black/60" />
                                                            {c.qr_target &&
                                                            c.qr_target !==
                                                                'edital'
                                                                ? ` · QR: ${c.qr_target}`
                                                                : ''}
                                                        </p>
                                                    </td>
                                                    {PHASES.map((phase, i) => (
                                                        <td
                                                            key={phase.label}
                                                            className="text-center align-middle"
                                                        >
                                                            <Box
                                                                filled={
                                                                    i <
                                                                    c.phase_done
                                                                }
                                                            />
                                                        </td>
                                                    ))}
                                                    <td className="px-1 align-middle">
                                                        <span className="block h-[6mm] border-b border-ink" />
                                                    </td>
                                                    <td className="py-[0.5mm] text-center align-middle">
                                                        <QrCode
                                                            contest={c}
                                                            size="size-[11mm]"
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </section>

                            {undated.length > 0 && (
                                <section
                                    aria-label="Sem data à vista"
                                    className="mb-[2.5mm]"
                                >
                                    <SectionTitle>
                                        sem data à vista
                                    </SectionTitle>
                                    <ul
                                        className={`grid gap-x-[3mm] ${landscape ? 'grid-cols-4' : 'grid-cols-5'}`}
                                    >
                                        {undated.map((c) => (
                                            <li
                                                key={c.id}
                                                className="flex break-inside-avoid gap-[1.5mm] border-t border-black/25 py-[1mm]"
                                            >
                                                <QrCode
                                                    contest={c}
                                                    size="size-[12mm]"
                                                />
                                                <div className="min-w-0 leading-tight">
                                                    <p className="text-[8.5pt] font-semibold">
                                                        {c.name}
                                                    </p>
                                                    {c.qr_host && (
                                                        <p className="truncate font-mono text-[6pt]">
                                                            {c.qr_host}
                                                        </p>
                                                    )}
                                                    <p className="mt-[0.8mm] text-[7pt]">
                                                        {'prev.: '}
                                                        <span className="inline-block w-[11mm] border-b border-black/50" />
                                                    </p>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            )}

                            <section
                                aria-label="Anotações"
                                className="flex min-h-[15mm] flex-1 flex-col"
                            >
                                <SectionTitle>anotações</SectionTitle>
                                <div className="wall-sheet-lines flex-1" />
                            </section>
                        </div>
                    </div>

                    <footer className="mt-1.5 border-t border-ink pt-1 font-mono text-[6.5pt] leading-tight">
                        ■ preto = o app já sabia ao imprimir · o resto é a lápis
                        · risque 1 quadrado da régua por dia · antes de
                        reimprimir, passe as marcas a lápis pro app — a folha
                        nova não as conhece
                    </footer>
                </article>
            </div>
        </>
    );
}
