import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { RegionOption } from '@/types/radar';

type StateFilterPopoverProps = {
    regions: RegionOption[];
    states: string[];
};

const NACIONAL = 'NACIONAL';

function allStateValues(regions: RegionOption[]): string[] {
    return regions.flatMap((region) => region.states.map((s) => s.value));
}

/** docs/03-ui-inventario.md → "Filtro de estados". */
export function StateFilterPopover({ regions, states }: StateFilterPopoverProps) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<string[]>(states);

    const total = allStateValues(regions).length;
    const label = draft.length >= total && draft.includes(NACIONAL) ? 'estados · todos' : `estados · ${draft.length}`;

    function toggle(value: string, checked: boolean) {
        setDraft((prev) => (checked ? [...new Set([...prev, value])] : prev.filter((v) => v !== value)));
    }

    function toggleRegion(region: RegionOption, checked: boolean) {
        const values = region.states.map((s) => s.value);
        setDraft((prev) => (checked ? [...new Set([...prev, ...values])] : prev.filter((v) => !values.includes(v))));
    }

    function markAll() {
        setDraft([...allStateValues(regions), NACIONAL]);
    }

    function clearAll() {
        setDraft([]);
    }

    function onlyNortheast() {
        const northeast = regions.find((r) => r.value === 'northeast');
        setDraft(northeast ? northeast.states.map((s) => s.value) : []);
    }

    function apply() {
        router.patch(
            '/preferences/states',
            { states: draft },
            {
                preserveScroll: true,
                only: ['preferences', 'visibleContests', 'regions'],
                onSuccess: () => setOpen(false),
            },
        );
    }

    function cancel() {
        setDraft(states);
        setOpen(false);
    }

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                if (next) {
setDraft(states);
}

                setOpen(next);
            }}
        >
            <PopoverTrigger asChild>
                <button type="button" className="text-body border border-line-strong bg-card px-3 py-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">{label} ▾</button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-[560px] border border-line-strong bg-card p-4 shadow-md">
                <div className="mb-3 flex items-center gap-4 border-b border-line pb-2">
                    <label className="flex items-center gap-2 text-sm font-semibold">
                        <Checkbox checked={draft.includes(NACIONAL)} onCheckedChange={(c) => toggle(NACIONAL, c === true)} />
                        Nacional
                    </label>
                    <button className="inline-flex min-h-6 items-center text-sm underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus" onClick={markAll} type="button">
                        marcar tudo
                    </button>
                    <button className="inline-flex min-h-6 items-center text-sm underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus" onClick={clearAll} type="button">
                        limpar
                    </button>
                    <button className="inline-flex min-h-6 items-center text-sm underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus" onClick={onlyNortheast} type="button">
                        só nordeste
                    </button>
                </div>

                <div className="grid grid-cols-5 gap-3">
                    {regions.map((region) => {
                        const values = region.states.map((s) => s.value);
                        const checkedCount = values.filter((v) => draft.includes(v)).length;
                        const regionState = checkedCount === 0 ? false : checkedCount === values.length ? true : 'indeterminate';

                        return (
                            <div key={region.value}>
                                <label className="mb-1 flex items-center gap-1.5 border-b border-line pb-1 text-sm font-semibold">
                                    <Checkbox
                                        checked={regionState}
                                        onCheckedChange={(c) => toggleRegion(region, c === true)}
                                    />
                                    {region.label}
                                </label>
                                <ul className="space-y-1">
                                    {region.states.map((state) => (
                                        <li key={state.value} className="flex items-center gap-1.5 text-sm">
                                            <Checkbox
                                                checked={draft.includes(state.value)}
                                                onCheckedChange={(c) => toggle(state.value, c === true)}
                                            />
                                            <span className="font-semibold">{state.value}</span>
                                            <span className="truncate">{state.label}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        );
                    })}
                </div>

                <div className="mt-3 flex justify-end gap-2 border-t border-line-strong pt-3">
                    <button className="border border-line-strong bg-card px-3 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus" onClick={cancel} type="button">
                        cancelar
                    </button>
                    <button
                        className="border border-line-strong bg-red-text px-3 py-1.5 text-sm font-semibold text-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        onClick={apply}
                        type="button"
                    >
                        aplicar
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
