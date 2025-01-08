<?php

namespace App\Overrides;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Drawer\ImplicitRouteBinding;
use Livewire\Features\SupportPageComponents\SupportPageComponents;

class InjectableSupportPageComponents extends SupportPageComponents
{
    static function gatherMountMethodParamsFromRouteParameters($component): array
    {
        // This allows for route parameters like "slug" in /post/{slug},
        // to be passed into a Livewire component's mount method...
        $route = request()->route();

        if (! $route) return [];

        try {
            $params = (new ImplicitRouteBinding(app()))
                ->resolveAllParameters($route, app($component::class));
        } catch (ModelNotFoundException $exception) {
            if (method_exists($route,'getMissing') && $route->getMissing()) {
                abort(
                    $route->getMissing()(request())
                );
            }

            throw $exception;
        }

        return $params;
    }

    protected static function resolvePageComponentRouteBindings()
    {
        // This method was introduced into Laravel 10.37.1 for this exact purpose...
        if (static::canSubstituteImplicitBindings()) {
            app('router')->substituteImplicitBindingsUsing(function ($container, $route, $default) {
                // If the current route is a Livewire page component...
                if ($componentClass = static::routeActionIsAPageComponent($route)) {
                    // Resolve and set all page component parameters to the current route...
                    (new \Livewire\Drawer\ImplicitRouteBinding($container))
                        ->resolveAllParameters($route, app($componentClass));
                } else {
                    // Otherwise, run the default Laravel implicit binding system...
                    $default();
                }
            });
        }
    }
}
