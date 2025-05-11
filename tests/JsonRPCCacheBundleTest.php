<?php

namespace Tourze\JsonRPCCacheBundle\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPCCacheBundle\JsonRPCCacheBundle;

class JsonRPCCacheBundleTest extends TestCase
{
    public function testInstantiation(): void
    {
        $bundle = new JsonRPCCacheBundle();
        $this->assertInstanceOf(JsonRPCCacheBundle::class, $bundle);
    }
} 