<?php

namespace App;

use Illuminate\Support\Collection;
use Spatie\CollectionMacros\Macros\Recursive;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        /** @see CollectionMacroServiceProvider::register() */
        collect([
            'recursive' => Recursive::class,
        ])->reject(fn ($class, $macro) => Collection::hasMacro($macro))
            ->each(fn ($class, $macro) => Collection::macro($macro, (new $class())()));
    }
}
