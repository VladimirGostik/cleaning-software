<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Ai\AnalyzesExpenses;
use App\Contracts\Ai\ChatsWithTools;
use App\Contracts\Ai\ExtractsTaskDraft;
use App\Contracts\Ai\RunsTasks;
use App\Contracts\GeneratesPaymentQr;
use App\Contracts\MergesPdfDocuments;
use App\Contracts\RendersContractPdf;
use App\Contracts\RendersInvoicePdf;
use App\Contracts\RendersQuotePdf;
use App\Contracts\ResolvesSupplierSignature;
use App\Contracts\SendsPush;
use App\Contracts\SubmitsEInvoices;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\TenantMembership;
use App\Models\User;
use App\Policies\PersonalAccessTokenPolicy;
use App\Services\Ai\LaravelAiAssistant;
use App\Services\Ai\LaravelAiExpenseAnalyzer;
use App\Services\Ai\LaravelAiTaskExtractor;
use App\Services\Ai\LaravelAiTaskRunner;
use App\Services\Efa\NullEInvoiceAccessPoint;
use App\Services\Pdf\ContractPdfService;
use App\Services\Pdf\InvoicePdfService;
use App\Services\Pdf\PayBySquareService;
use App\Services\Pdf\QpdfMerger;
use App\Services\Pdf\QuotePdfService;
use App\Services\Pdf\SupplierSignatureResolver;
use App\Services\Push\FirebasePushSender;
use App\Support\PrecognitiveDataValidatorResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
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
        $this->app->bind(MergesPdfDocuments::class, QpdfMerger::class);
        $this->app->bind(SendsPush::class, FirebasePushSender::class);
        $this->app->bind(AnalyzesExpenses::class, LaravelAiExpenseAnalyzer::class);
        $this->app->bind(ChatsWithTools::class, LaravelAiAssistant::class);
        $this->app->bind(ExtractsTaskDraft::class, LaravelAiTaskExtractor::class);
        $this->app->bind(RunsTasks::class, LaravelAiTaskRunner::class);
        $this->app->bind(SubmitsEInvoices::class, fn (): SubmitsEInvoices => match (config('efa.driver')) {
            default => new NullEInvoiceAccessPoint,
        });

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

        Gate::policy(PersonalAccessToken::class, PersonalAccessTokenPolicy::class);

        Relation::morphMap([
            'tenant_membership' => TenantMembership::class,
            'cleaning_object' => CleaningObject::class,
            'client' => Client::class,
        ]);

        RateLimiter::for('devices', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? 'ip:'.get_client_ip()));
        RateLimiter::for('ai-analyze', fn (Request $request) => Limit::perMinute(config()->integer('ai.throttle.analyze', 20))->by($request->user()?->getAuthIdentifier() ?? 'ip:'.get_client_ip()));
        RateLimiter::for('mcp', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();
            $tokenId = $token instanceof PersonalAccessToken ? (string) $token->id : null;

            return Limit::perMinute(config()->integer('ai.mcp_throttle', 120))->by($tokenId !== null ? 'mcp-token:'.$tokenId : 'ip:'.get_client_ip());
        });
        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute(config()->integer('ai.throttle.assistant', 30))->by($request->user()?->getAuthIdentifier() ?? 'ip:'.get_client_ip()));
        RateLimiter::for('document-preview', fn (Request $request) => Limit::perMinute(30)->by($request->user() instanceof User ? 'user:'.$request->user()->id : 'ip:'.get_client_ip()));
        RateLimiter::for('task-attachments', fn (Request $request) => Limit::perMinute(60)->by($request->user() instanceof User ? 'user:'.$request->user()->id : 'ip:'.get_client_ip()));
        RateLimiter::for('task-comments', fn (Request $request) => Limit::perMinute(30)->by($request->user() instanceof User ? 'user:'.$request->user()->id : 'ip:'.get_client_ip()));
        RateLimiter::for('task-ai-runs', fn (Request $request) => Limit::perMinute(10)->by($request->user() instanceof User ? 'user:'.$request->user()->id : 'ip:'.get_client_ip()));
        RateLimiter::for('mobile-token', fn (Request $request) => Limit::perMinute(30)->by('ip:'.get_client_ip()));
        RateLimiter::for('mobile-login', fn (Request $request): array => [
            Limit::perMinute(10)->by('ip:'.get_client_ip()),
            Limit::perMinute(5)->by('email:'.Str::lower($request->string('email')->toString()).'|ip:'.get_client_ip()),
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
