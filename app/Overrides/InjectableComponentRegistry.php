<?php

namespace App\Overrides;

use Livewire\Mechanisms\ComponentRegistry;

class InjectableComponentRegistry extends ComponentRegistry
{
    function new($nameOrClass, $id = null)
    {
        [$class, $name] = $this->getNameAndClass($nameOrClass);

        $component = app($class);

        $component->setId($id ?: str()->random(20));

        $component->setName($name);

        return $component;
    }
}
