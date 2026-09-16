<?php

namespace App\Services\Radar;

use App\Exceptions\Radar\ContestDismissedException;
use App\Exceptions\Radar\PinSlotsFullException;
use App\Models\Contest;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gerencia a maleta de 12 slots: fixar, desafixar, reordenar. Puro sobre o
 * model Contest — sem chamada de LLM. Ver docs/00-spec.md → "Pin (maleta)".
 */
class PinService
{
    /**
     * Fixa no primeiro slot livre, ou no slot indicado.
     *
     * @throws ContestDismissedException concurso descartado não pode ser fixado.
     * @throws PinSlotsFullException maleta cheia (sem slot indicado) ou slot já ocupado.
     */
    public function pin(Contest $contest, ?int $slot = null): Contest
    {
        if ($contest->isDismissed()) {
            throw new ContestDismissedException('Concurso descartado não pode ser fixado.');
        }

        if ($slot === null) {
            $slot = $this->firstFreeSlot(excludingContestId: $contest->id);

            if ($slot === null) {
                throw new PinSlotsFullException('Maleta cheia — desafixe algum item antes de fixar outro.');
            }
        } else {
            if ($slot < 0 || $slot >= Contest::PIN_SLOTS) {
                throw new InvalidArgumentException("Slot inválido: {$slot}.");
            }

            $occupied = Contest::query()
                ->where('pinned_slot', $slot)
                ->where('id', '!=', $contest->id)
                ->exists();

            if ($occupied) {
                throw new PinSlotsFullException("Slot {$slot} já está ocupado.");
            }
        }

        $contest->pinned_slot = $slot;
        $contest->save();

        return $contest->refresh();
    }

    public function unpin(Contest $contest): Contest
    {
        $contest->pinned_slot = null;
        $contest->save();

        return $contest->refresh();
    }

    /**
     * Reordena a maleta inteira. `$order` tem até 12 posições (índice = slot),
     * cada valor um id de concurso ou null (slot vazio).
     *
     * @param  array<int, int|null>  $order
     */
    public function reorder(array $order): void
    {
        if (count($order) > Contest::PIN_SLOTS) {
            throw new InvalidArgumentException('Mais posições que slots disponíveis na maleta.');
        }

        DB::transaction(function () use ($order) {
            $ids = array_values(array_filter($order, fn ($id) => $id !== null));

            // Limpa os pins envolvidos primeiro para não colidir com o unique
            // index de pinned_slot ao reatribuir.
            if ($ids !== []) {
                Contest::query()->whereIn('id', $ids)->update(['pinned_slot' => null]);
            }

            foreach ($order as $slot => $contestId) {
                if ($contestId === null) {
                    continue;
                }

                Contest::query()->where('id', $contestId)->update(['pinned_slot' => $slot]);
            }
        });
    }

    private function firstFreeSlot(?int $excludingContestId = null): ?int
    {
        $taken = Contest::query()
            ->whereNotNull('pinned_slot')
            ->when($excludingContestId, fn ($q) => $q->where('id', '!=', $excludingContestId))
            ->pluck('pinned_slot')
            ->all();

        for ($i = 0; $i < Contest::PIN_SLOTS; $i++) {
            if (! in_array($i, $taken, true)) {
                return $i;
            }
        }

        return null;
    }
}
