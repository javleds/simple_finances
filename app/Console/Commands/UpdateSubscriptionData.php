<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

class UpdateSubscriptionData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-subscription-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read the subscription payments to generate the previos and next payment dates.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        progress('Update subscriptions', Subscription::withoutGlobalScopes()->get(), function (Subscription $subscription) {
            $subscription->withoutGlobalScopes()->update([
                'previous_payment_date' => $subscription->payments()->where('status', PaymentStatus::Paid)->first()?->scheduled_at,
                'next_payment_date' => $subscription->payments()->where('status', PaymentStatus::Pending)->first()?->scheduled_at,
            ]);
        });

        return self::SUCCESS;
    }
}
