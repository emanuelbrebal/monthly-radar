<?php

namespace App\Services\Radar;

/**
 * Regra de casamento do filtro de estados (docs/06-filtro-estados.md).
 * Puro — sem I/O, sem model. Tem que bater com Contest::scopeMatchingStates
 * (teste de paridade).
 */
class StateFilter
{
    /**
     * Um concurso casa com a seleção quando qualquer uma vale:
     * 1. seleção vazia;
     * 2. nationwide e "NACIONAL" está selecionado;
     * 3. interseção entre states e seleção não é vazia;
     * 4. states vazio e não nationwide (local a definir).
     *
     * @param  array<int, string>  $selection  siglas + "NACIONAL"
     * @param  array<int, string>  $states
     */
    public function matches(array $selection, array $states, bool $nationwide): bool
    {
        if ($selection === []) {
            return true;
        }

        if ($nationwide && in_array('NACIONAL', $selection, true)) {
            return true;
        }

        if (array_intersect($states, $selection) !== []) {
            return true;
        }

        return $states === [] && ! $nationwide;
    }
}
