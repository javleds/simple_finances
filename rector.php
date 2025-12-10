<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Set\LaravelSetList;

return static function (RectorConfig $config): void {
    $config->paths([
        __DIR__ . '/app',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ]);

    $config->phpVersion(PhpVersion::PHP_82);

    $config->skip([
        __DIR__ . '/bootstrap/cache/*',
        __DIR__ . '/storage/*',
        __DIR__ . '/public/*',
        __DIR__ . '/vendor/*',
        __DIR__ . '/node_modules/*',
    ]);

    $config->sets([
        LaravelSetList::LARAVEL_100,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::STRICT_BOOLEANS,
        SetList::EARLY_RETURN,
        SetList::PHP_82,
    ]);

    $config->importNames();
};
