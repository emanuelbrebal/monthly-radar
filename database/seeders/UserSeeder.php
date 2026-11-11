<?php

namespace Database\Seeders;

use App\Enums\BrazilianState;
use App\Models\User;
use App\ValueObjects\UserPreferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public const MIN_PRODUCTION_PASSWORD_LENGTH = 12;

    /**
     * Cria (ou atualiza) o único usuário do app a partir do .env.
     * Sem cadastro público — este é o único ponto de entrada de usuário.
     */
    public function run(): void
    {
        $email = config('radar.seed_user.email');
        $password = config('radar.seed_user.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->command->warn('RADAR_USER_EMAIL / RADAR_USER_PASSWORD não configurados no .env — UserSeeder pulado.');

            return;
        }

        // App exposto na internet: em produção não aceita a senha de exemplo
        // nem senha curta (docs/07-deploy.md). Local segue livre.
        if (app()->isProduction() && ($password === 'change-me-before-deploy' || mb_strlen($password) < self::MIN_PRODUCTION_PASSWORD_LENGTH)) {
            $this->command->error('RADAR_USER_PASSWORD fraca para produção (mínimo '.self::MIN_PRODUCTION_PASSWORD_LENGTH.' caracteres, sem o valor de exemplo) — UserSeeder pulado.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Emanuel',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        // Rodar de novo (ex.: trocar a senha depois de restaurar um dump) não
        // pode apagar o filtro de estados que o usuário já escolheu.
        if (! $user->wasRecentlyCreated) {
            return;
        }

        // "Tudo marcado" no cadastro inicial: aceito lotação em qualquer
        // estado (docs/06-filtro-estados.md). Equivale a uma seleção vazia
        // no StateFilter, mas explícito aqui como o doc pede.
        $allStates = array_map(fn (BrazilianState $s) => $s->value, BrazilianState::cases());

        // 'preferences' fica fora do #[Fillable] do model (só name/email/
        // password) — não é dado de cadastro, é atribuído direto aqui.
        $user->preferences = new UserPreferences(states: [...$allStates, 'NACIONAL']);
        $user->save();
    }
}
