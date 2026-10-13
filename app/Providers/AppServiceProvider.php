<?php

namespace App\Providers;

use App\Services\Radar\Llm\AnthropicProvider;
use App\Services\Radar\Llm\NullProvider;
use App\Services\Radar\LlmProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // CLAUDE.md regra 3/4: sem ANTHROPIC_API_KEY, o app inteiro usa o
        // provider nulo — enriquecimento fica enrichment_status=skipped,
        // nenhuma tela quebra.
        $this->app->bind(LlmProvider::class, fn () => filled(config('radar.llm.api_key'))
            ? new AnthropicProvider
            : new NullProvider,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
