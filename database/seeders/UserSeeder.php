<?php

namespace Database\Seeders;

use App\Enums\BrazilianState;
use App\Models\User;
use App\ValueObjects\UserPreferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Cria (ou atualiza) o único usuário do app a partir do .env.
     * Sem cadastro público — este é o único ponto de entrada de usuário.
     *
     * Usa env() diretamente (não config/radar.php): credenciais não são
     * parâmetro de domínio e config/radar.php só guarda as chaves definidas
     * em docs/04-plano-execucao.md (sources, area_keywords, boards, enrich,
     * llm, timezone).
     */
    public function run(): void
    {
        $email = env('RADAR_USER_EMAIL');
        $password = env('RADAR_USER_PASSWORD');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->command->warn('RADAR_USER_EMAIL / RADAR_USER_PASSWORD não configurados no .env — UserSeeder pulado.');

            return;
        }

        // "Tudo marcado" no cadastro inicial: aceito lotação em qualquer
        // estado (docs/06-filtro-estados.md). Equivale a uma seleção vazia
        // no StateFilter, mas explícito aqui como o doc pede.
        $allStates = array_map(fn (BrazilianState $s) => $s->value, BrazilianState::cases());

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Emanuel',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        // 'preferences' fica fora do #[Fillable] do model (só name/email/
        // password) — não é dado de cadastro, é atribuído direto aqui.
        $user->preferences = new UserPreferences(states: [...$allStates, 'NACIONAL']);
        $user->save();
    }
}
