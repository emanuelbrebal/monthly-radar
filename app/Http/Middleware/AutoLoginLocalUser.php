<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entra direto como o único usuário quando o app roda só na máquina do dono.
 *
 * Três travas, todas obrigatórias: config('radar.auto_login'), APP_ENV=local
 * e IP de loopback. Fora disso não faz nada — o login do Fortify continua
 * valendo (deploy na VPS).
 */
class AutoLoginLocalUser
{
    private const LOOPBACK = ['127.0.0.1', '::1'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldAutoLogin($request)) {
            $user = $this->resolveUser();

            if ($user !== null) {
                Auth::login($user, remember: true);
            }
        }

        return $next($request);
    }

    private function shouldAutoLogin(Request $request): bool
    {
        return config('radar.auto_login') === true
            && app()->environment('local')
            && in_array($request->ip(), self::LOOPBACK, true)
            && ! Auth::check();
    }

    private function resolveUser(): ?User
    {
        $email = config('radar.seed_user.email');

        if (is_string($email) && $email !== '') {
            $user = User::query()->where('email', $email)->first();

            if ($user !== null) {
                return $user;
            }
        }

        return User::query()->orderBy('id')->first();
    }
}
