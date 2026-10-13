<?php

namespace App\Services\Radar;

use App\Enums\EditalStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calcula o status efetivo de um concurso a partir do status raspado/enriquecido
 * e das datas de edital. Puro — sem I/O, sem model. Ver docs/02-pipeline.md → "4. Status efetivo".
 */
class ContestStatusResolver
{
    public function resolve(
        EditalStatus $status,
        ?CarbonInterface $registrationStart,
        ?CarbonInterface $registrationEnd,
        ?CarbonInterface $examDate,
        ?CarbonInterface $today = null,
    ): EditalStatus {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();

        // 1. suspended ou finished → mantém, ignora as demais regras.
        if ($status === EditalStatus::Suspended || $status === EditalStatus::Finished) {
            return $status;
        }

        $resolved = $status;

        // 2. exam_date < hoje → no mínimo exam_done.
        if ($examDate !== null && $examDate->startOfDay()->lt($today)) {
            $resolved = $this->atLeast($resolved, EditalStatus::ExamDone);
        }

        // 3. registration_end < hoje → no mínimo registration_closed.
        if ($registrationEnd !== null && $registrationEnd->startOfDay()->lt($today)) {
            $resolved = $this->atLeast($resolved, EditalStatus::RegistrationClosed);
        }

        // 4. registration_start ≤ hoje ≤ registration_end e status < registration_open → registration_open.
        if (
            $registrationStart !== null
            && $registrationEnd !== null
            && $registrationStart->startOfDay()->lte($today)
            && $registrationEnd->startOfDay()->gte($today)
            && $resolved->order() < EditalStatus::RegistrationOpen->order()
        ) {
            $resolved = EditalStatus::RegistrationOpen;
        }

        // 5. registration_start > hoje e status ≥ registration_open → published (retificado/adiado).
        if (
            $registrationStart !== null
            && $registrationStart->startOfDay()->gt($today)
            && $resolved->order() >= EditalStatus::RegistrationOpen->order()
        ) {
            $resolved = EditalStatus::Published;
        }

        // 6. senão → status armazenado (já é o valor de $resolved nesse ponto).
        return $resolved;
    }

    /**
     * Maior entre o status atual e o piso sugerido, na ordem da tabela da spec.
     */
    private function atLeast(EditalStatus $current, EditalStatus $floor): EditalStatus
    {
        return $current->order() >= $floor->order() ? $current : $floor;
    }
}
