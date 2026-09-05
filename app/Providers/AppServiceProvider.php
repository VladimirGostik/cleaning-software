<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GeneratesPaymentQr;
use App\Contracts\RendersContractPdf;
use App\Contracts\RendersInvoicePdf;
use App\Contracts\RendersQuotePdf;
use App\Listeners\StampInvoiceSentAt;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Quote;
use App\Models\ScheduledJob;
use App\Models\TenantMembership;
use App\Policies\ContractPolicy;
use App\Policies\ContractTemplatePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ObjectPolicy;
use App\Policies\QuotePolicy;
use App\Policies\ScheduledJobPolicy;
use App\Policies\TenantMembershipPolicy;
use App\Services\Pdf\ContractPdfService;
use App\Services\Pdf\InvoicePdfService;
use App\Services\Pdf\PayBySquareService;
use App\Services\Pdf\QuotePdfService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GeneratesPaymentQr::class, PayBySquareService::class);
        $this->app->bind(RendersInvoicePdf::class, InvoicePdfService::class);
        $this->app->bind(RendersContractPdf::class, ContractPdfService::class);
        $this->app->bind(RendersQuotePdf::class, QuotePdfService::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // Morph map — prevents FQCN stored in contractable_type column
        Relation::morphMap([
            'cleaning_object' => CleaningObject::class,
            'tenant_membership' => TenantMembership::class,
        ]);

        // CleaningObject uses a non-standard class/policy name pair — explicit registration
        // required because auto-discovery expects App\Policies\CleaningObjectPolicy, not ObjectPolicy.
        Gate::policy(CleaningObject::class, ObjectPolicy::class);

        // Explicit policy bindings for contracts (standard name pairs auto-discover but explicit is safer)
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(ContractTemplate::class, ContractTemplatePolicy::class);

        Gate::policy(Quote::class, QuotePolicy::class);

        // TenantMembership — auto-discovery would find TenantMembershipPolicy, but explicit is safer.
        Gate::policy(TenantMembership::class, TenantMembershipPolicy::class);

        // ScheduledJob — explicit binding because model class name doesn't match ScheduledJobPolicy pattern.
        Gate::policy(ScheduledJob::class, ScheduledJobPolicy::class);

        // DatabaseNotification is framework-owned — no auto-discovery, must register explicitly.
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);

        // StampInvoiceSentAt listens to the framework NotificationSent event.
        // Auto-discovery does not reliably find framework events as targets — register explicitly.
        Event::listen(NotificationSent::class, StampInvoiceSentAt::class);

        RateLimiter::for('api', function (Request $r): Limit {
            return Limit::perMinute(60)
                ->by($r->user()?->id ?: get_client_ip());
        });

        RateLimiter::for('login', function (Request $r): array {
            return [
                Limit::perMinute(500),
                Limit::perMinute(5)->by('email:' . $r->input('email')),
            ];
        });

        RateLimiter::for('password-reset', function (Request $r): array {
            return [
                Limit::perMinute(3)->by('email:' . $r->input('email')),
                Limit::perMinute(10)->by('ip:' . get_client_ip()),
            ];
        });

        RateLimiter::for('password-reset-confirm', function (): Limit {
            return Limit::perMinute(10)->by(get_client_ip());
        });

        RateLimiter::for('ico-lookup', function (): Limit {
            return Limit::perMinute(30)->by('ip:' . get_client_ip());
        });

        RateLimiter::for('invitation-accept', function (Request $r): Limit {
            return Limit::perMinute(5)->by('ip:' . get_client_ip());
        });
    }
}
