<?php

namespace App\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * `users.preferences` → {states: string[]}. Siglas de BrazilianState + "NACIONAL".
 * Vazio = todos os estados (docs/06-filtro-estados.md).
 */
class UserPreferences implements Castable
{
    /**
     * @param  array<int, string>  $states
     */
    public function __construct(public readonly array $states = []) {}

    /**
     * @return CastsAttributes<UserPreferences, array{states?: array<int, string>}>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes): UserPreferences
            {
                $decoded = $value === null ? [] : json_decode($value, true);

                return new UserPreferences(states: $decoded['states'] ?? []);
            }

            /**
             * @return array<string, string>
             */
            public function set($model, string $key, $value, array $attributes): array
            {
                $states = $value instanceof UserPreferences ? $value->states : ($value['states'] ?? []);

                return [$key => json_encode(['states' => array_values($states)], JSON_THROW_ON_ERROR)];
            }
        };
    }
}
