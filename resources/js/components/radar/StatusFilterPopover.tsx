import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { EditalStatusValue } from '@/types/radar';

export type StatusOption = { value: EditalStatusValue; label: string };

type StatusFilterPopoverProps = {
    options: StatusOption[];
    selected: Set<EditalStatusValue>;
    onChange: (next: Set<EditalStatusValue>) => void;
    disabled?: boolean;
};

/** Filtro de status da caixa de itens: um controle compacto no lugar da fileira de chips. */
export function StatusFilterPopover({ options, selected, onChange, disabled = false }: StatusFilterPopoverProps) {
    const label = selected.size > 0 ? `status · ${selected.size}` : 'status';

    function toggle(value: EditalStatusValue, checked: boolean) {
        const next = new Set(selected);

        if (checked) {
            next.add(value);
        } else {
            next.delete(value);
        }

        onChange(next);
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    disabled={disabled}
                    className="t-body min-h-6 rounded-md border border-line-strong bg-card px-3 py-1 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:text-muted-fg"
                >
                    {label} ▾
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-64 rounded-lg border border-ink bg-card p-3">
                <ul className="space-y-1">
                    {options.map((opt) => (
                        <li key={opt.value}>
                            <label className="t-body flex min-h-6 cursor-pointer items-center gap-2 text-ink">
                                <Checkbox checked={selected.has(opt.value)} onCheckedChange={(c) => toggle(opt.value, c === true)} />
                                {opt.label}
                            </label>
                        </li>
                    ))}
                </ul>
                <div className="mt-3 flex justify-end border-t border-line-strong pt-2">
                    <button
                        type="button"
                        onClick={() => onChange(new Set())}
                        disabled={selected.size === 0}
                        className="t-body min-h-6 rounded-sm px-2 text-ink underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:text-muted-fg"
                    >
                        limpar
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
