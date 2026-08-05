<?php

namespace App\Providers;

use App\Events\Billing\InvoiceGenerated;
use App\Events\Billing\PaymentReceived;
use App\Events\Billing\SubscriptionReactivated;
use App\Events\Billing\SubscriptionSuspended;
use App\Listeners\Billing\LogInvoiceGenerated;
use App\Listeners\Billing\LogPaymentReceived;
use App\Listeners\Billing\LogReactivation;
use App\Listeners\Billing\LogSuspension;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        InvoiceGenerated::class => [
            LogInvoiceGenerated::class,
        ],
        PaymentReceived::class => [
            LogPaymentReceived::class,
            \App\Listeners\Billing\AutoReactivateOnPayment::class,
        ],
        SubscriptionSuspended::class => [
            LogSuspension::class,
        ],
        SubscriptionReactivated::class => [
            LogReactivation::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}