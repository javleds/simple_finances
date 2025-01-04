<?php

namespace App\Filament\Pages;

use App\Models\Subscription;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class Projection extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.resources.subscription-resource.pages.projection';

    public Collection $subscriptions;
    public string $pageTitle = '';
    public float $total = 0.0;

    public function getTitle(): string|Htmlable
    {
        return sprintf('Proyección %s', $this->pageTitle);
    }

    public function mount(): void
    {
        $type = request('type');

        $this->pageTitle = $type === 'monthly' ? 'mensual' : 'anual';
        $this->subscriptions = Subscription::whereNull('finished_at')
            ->get()
            ->map(function (Subscription $subscription) use ($type) {
                if ($type === 'monthly' && $subscription->isMonthly()) {
                    return $subscription;
                }

                if ($type === 'monthly' && $subscription->isYearly()) {
                    $subscription->amount /= 12;

                    return $subscription;
                }

                if ($type === 'monthly' && $subscription->isDaily()) {
                    $subscription->amount = ($subscription->amount / $subscription->frequency_unit) * 30.4;

                    return $subscription;
                }

                if ($type === 'yearly' && $subscription->isMonthly()) {
                    $subscription->amount *= 12;

                    return $subscription;
                }

                if ($type === 'yearly' && $subscription->isYearly()) {
                    return $subscription;
                }

                if ($type === 'yearly' && $subscription->isDaily()) {
                    $subscription->amount = ($subscription->amount / $subscription->frequency_unit) * 365;

                    return $subscription;
                }

                return $subscription;
            });

        $this->total = $this->subscriptions->sum('amount');
    }
}
