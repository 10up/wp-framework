<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;

return RectorConfig::configure()
    ->withPaths([
	    __DIR__ . '/src',
	    __DIR__ . '/fixtures',
    ])
	->withSkip([
		__DIR__ . '/**/node_modules/**',
		__DIR__ . '/**/vendor/**',
		__DIR__ . '/**/dist/**',

	])
    ->withPhpSets(php83: true)
	->withTypeCoverageLevel(49)
	->withSkip([
		Rector\Php81\Rector\Array_\FirstClassCallableRector::class,
		Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector::class,
		Rector\Php70\Rector\StmtsAwareInterface\IfIssetToCoalescingRector::class,
		Rector\Php53\Rector\Ternary\TernaryToElvisRector::class,
		Rector\Php81\Rector\Property\ReadOnlyPropertyRector::class,
	]);
