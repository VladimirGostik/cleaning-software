<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Support\PrecognitiveDataValidatorResolver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Validator;
use ReflectionClass;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DataValidatorResolver::class, PrecognitiveDataValidatorResolver::class);

        $this->app->beforeResolving(BaseData::class, function (string $class, array $parameters, $app): void {
            /** @var Request $request */
            $request = $app['request'];

            if (! $request->isAttemptingPrecognition()) {
                return;
            }

            $app->bind($class, function () use ($class, $request, $app) {
                $payload = $request->all();
                $resolver = $app->make(DataValidatorResolver::class);

                /** @var Validator $validator */
                $validator = $resolver->execute($class, $payload);
                $validator->validate();

                return (new ReflectionClass($class))->newInstanceWithoutConstructor();
            });
        });
    }

    public function boot(): void
    {
        $this->loadJsonTranslations();

        Event::listen(Login::class, [LogAuthenticationActivity::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthenticationActivity::class, 'handleLogout']);
        Event::listen(Failed::class, [LogAuthenticationActivity::class, 'handleFailed']);
    }

    private function loadJsonTranslations(): void
    {
        foreach (glob(resource_path('lang/*/') ?: []) as $langDir) {
            $locale = basename($langDir);
            foreach (glob($langDir.'*.json') ?: [] as $file) {
                $group = basename($file, '.json');
                $translations = json_decode((string) file_get_contents($file), true) ?? [];
                $lines = [];
                foreach ($translations as $key => $value) {
                    $lines["{$group}.{$key}"] = $value;
                }
                Lang::addLines($lines, $locale);
            }
        }
    }
}
