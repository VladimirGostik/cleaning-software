<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GeneratesPaymentQr;
use App\Contracts\RendersContractPdf;
use App\Contracts\RendersInvoicePdf;
use App\Contracts\RendersQuotePdf;
use App\Contracts\ResolvesSupplierSignature;
use App\Models\CleaningObject;
use App\Models\TenantMembership;
use App\Services\Pdf\ContractPdfService;
use App\Services\Pdf\InvoicePdfService;
use App\Services\Pdf\PayBySquareService;
use App\Services\Pdf\QuotePdfService;
use App\Services\Pdf\SupplierSignatureResolver;
use App\Support\PrecognitiveDataValidatorResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\ValidateableData;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DataValidatorResolver::class, PrecognitiveDataValidatorResolver::class);

        $this->app->bind(RendersInvoicePdf::class, InvoicePdfService::class);
        $this->app->bind(GeneratesPaymentQr::class, PayBySquareService::class);
        $this->app->bind(RendersQuotePdf::class, QuotePdfService::class);
        $this->app->bind(RendersContractPdf::class, ContractPdfService::class);
        $this->app->bind(ResolvesSupplierSignature::class, SupplierSignatureResolver::class);

        $this->app->beforeResolving(BaseData::class, function (string $class, array $parameters, Container $app): void {
            $request = $app->make(Request::class);

            if (! $request->isAttemptingPrecognition()) {
                return;
            }

            $app->bind($class, function () use ($class, $request, $app): object {
                $payload = $request->all();

                /** @var DataValidatorResolver $resolver */
                $resolver = $app->make(DataValidatorResolver::class);

                /** @var class-string<ValidateableData&BaseData<mixed, mixed, array-key>> $dataClass */
                $dataClass = $class;

                $resolver->execute($dataClass, $payload)->validate();

                /** @var ReflectionClass<object> $reflection */
                $reflection = new ReflectionClass($dataClass);

                return $reflection->newInstanceWithoutConstructor();
            });
        });
    }

    public function boot(): void
    {
        $this->loadJsonTranslations();

        Relation::morphMap([
            'tenant_membership' => TenantMembership::class,
            'cleaning_object' => CleaningObject::class,
        ]);

        RateLimiter::for('invitation-accept', fn (Request $request) => Limit::perMinute(5)->by('ip:'.get_client_ip()));
    }

    private function loadJsonTranslations(): void
    {
        foreach (glob(resource_path('lang/*/')) ?: [] as $langDir) {
            $locale = basename($langDir);

            foreach (glob($langDir.'*.json') ?: [] as $file) {
                $group = basename($file, '.json');

                $translations = json_decode((string) file_get_contents($file), true);

                if (! is_array($translations)) {
                    continue;
                }

                /** @var array<string, string> $translations */
                $lines = [];

                foreach ($translations as $key => $value) {
                    $lines["{$group}.{$key}"] = $value;
                }

                Lang::addLines($lines, $locale);
            }
        }
    }
}
