<?php

namespace Tourze\JsonRPCCacheBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\JsonRPCCacheBundle\JsonRPCCacheBundle;

class JsonRPCCacheBundleTest extends TestCase
{
    /**
     * 测试 Bundle 类可以正确实例化
     */
    public function testInstantiation_shouldSucceed(): void
    {
        $bundle = new JsonRPCCacheBundle();
        $this->assertInstanceOf(JsonRPCCacheBundle::class, $bundle);
    }

    /**
     * 测试 Bundle 类继承自正确的父类
     */
    public function testInheritance_shouldInheritFromBundle(): void
    {
        $bundle = new JsonRPCCacheBundle();
        $this->assertInstanceOf(Bundle::class, $bundle);
    }
}
