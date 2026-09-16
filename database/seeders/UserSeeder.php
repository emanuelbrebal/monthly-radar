<?php

namespace Database\Seeders;

use App\Models\User;
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

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Emanuel',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );
    }
}
