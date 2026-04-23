<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;

/**
 * Rector configuration for the Trilink backend. Keep the rule set
 * conservative — the aim is type-coverage + dead-code removal + modernization
 * to PHP 8.3 idioms, not a big-bang rewrite.
 *
 * Run:
 *   vendor/bin/rector process --dry-run
 */
return static function (RectorConfig $config): void {
    $config->paths([
        __DIR__.'/app',
        __DIR__.'/database/factories',
        __DIR__.'/database/seeders',
        __DIR__.'/routes',
    ]);

    $config->skip([
        __DIR__.'/storage',
        __DIR__.'/bootstrap/cache',
        __DIR__.'/vendor',
        // Blade views + migrations — leave alone, they get broken by auto-rewrites.
        __DIR__.'/resources/views',
        __DIR__.'/database/migrations',
        // Legacy God-files we'll refactor by hand in Phase 3.
        CallableThisArrayToAnonymousFunctionRector::class,
        AddTypeToConstRector::class,
    ]);

    $config->sets([
        LevelSetList::UP_TO_PHP_83,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
    ]);

    // Opt-in: stricter typed properties from assignments.
    $config->rule(TypedPropertyFromAssignsRector::class);

    $config->parallel();
    $config->importNames(importDocBlockNames: false);
};
