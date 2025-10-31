<?php

declare(strict_types=1);

namespace Tourze\JsonRPCCacheBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPCCacheBundle\JsonRPCCacheBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(JsonRPCCacheBundle::class)]
#[RunTestsInSeparateProcesses]
final class JsonRPCCacheBundleTest extends AbstractBundleTestCase
{
}
