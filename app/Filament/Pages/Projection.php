<?php

namespace App\Filament\Pages;

use App\Dto\SubscriptionProjection;
use App\Filament\Resources\SubscriptionResource;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class Projection extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.resources.subscription-resource.pages.projection';

    public Collection $subscriptions;

    public string $pageTitle = '';

    public float $total = 0.0;

    public function getTitle(): string|Htmlable
    {
        return sprintf('Proyección %s', $this->pageTitle);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('return')
                ->color('secondary')
                ->label('Regresar')
                ->url(SubscriptionResource::getUrl()),
        ];
    }

    public function mount(): void
    {
        $type = request('type');

        $this->pageTitle = $type === 'monthly' ? 'mensual' : 'anual';
        $this->subscriptions = Subscription::whereNull('finished_at')
            ->get()
            ->map(function (Subscription $subscription) use ($type) {
                if ($type === 'monthly' && $subscription->isMonthly()) {
                    $computedAmount = $subscription->amount;

                    return SubscriptionProjection::fromSubscriptionProjection($subscription, $computedAmount)->toArray();
                }

                if ($type === 'monthly' && $subscription->isYearly()) {
                    $computedAmount = $subscription->amount / 12;

                    return SubscriptionProjection::fromSubscriptionProjection($subscription, $computedAmount)->toArray();
                }

                if ($type === 'monthly' && $subscription->isDaily()) {
                    $computedAmount = ($subscription->amount / $subscription->frequency_unit) * 30.4;

                    return SubscriptionProjection::fromSubscriptionProjection($subscription, $computedAmount)->toArray();
                }

                if ($type === 'yearly' && $subscription->isMonthly()) {
                    $computedAmount = $subscription->amount * 12;

                    return SubscriptionProjection::fromSubscriptionProjection($subscription, $computedAmount)->toArray();
                }

                if ($type === 'yearly' && $subscription->isYearly()) {
                    $computedAmount = $subscription->amount;

                    return SubscriptionProjection::fromSubscriptionProjection($subscription, $computedAmount)->toArray();
                }

                if ($type === 'yearly' && $subscription->isDaily()) {
                    $computedAmount = ($subscription->amount / $subscription->frequency_unit) * 365;

                    return SubscriptionProjection::fromSubscriptionProjection($subscription, $computedAmount)->toArray();
                }

                $computedAmount = $subscription->amount;

                return SubscriptionProjection::fromSubscriptionProjection($subscription, $computedAmount)->toArray();
            })
            ->sortBy('projectionAmount', descending: true);

        $this->total = $this->subscriptions->sum('projectionAmount');
    }
}
