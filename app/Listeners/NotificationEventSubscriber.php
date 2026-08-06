<?php

namespace App\Listeners;

use App\Events\Billing\InvoiceGenerated;
use App\Events\Billing\PaymentReceived;
use App\Events\Billing\SubscriptionReactivated;
use App\Events\Billing\SubscriptionSuspended;
use App\Notifications\InvoiceGeneratedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ServiceReactivatedNotification;
use App\Notifications\ServiceSuspendedNotification;
use Illuminate\Events\Dispatcher;

class NotificationEventSubscriber
{
    public function handleInvoiceGenerated(InvoiceGenerated $event): void
    {
        $customer = $event->invoice->customer;
        if ($customer) {
            $customer->notify(new InvoiceGeneratedNotification($event->invoice));
        }
    }

    public function handlePaymentReceived(PaymentReceived $event): void
    {
        $customer = $event->payment->customer;
        if ($customer) {
            $customer->notify(new PaymentReceivedNotification($event->payment));
        }
    }

    public function handleSubscriptionSuspended(SubscriptionSuspended $event): void
    {
        $customer = $event->subscription->customer;
        if ($customer) {
            $customer->notify(new ServiceSuspendedNotification($event->subscription));
        }
    }

    public function handleSubscriptionReactivated(SubscriptionReactivated $event): void
    {
        $customer = $event->subscription->customer;
        if ($customer) {
            $customer->notify(new ServiceReactivatedNotification($event->subscription));
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            InvoiceGenerated::class => 'handleInvoiceGenerated',
            PaymentReceived::class => 'handlePaymentReceived',
            SubscriptionSuspended::class => 'handleSubscriptionSuspended',
            SubscriptionReactivated::class => 'handleSubscriptionReactivated',
        ];
    }
}
