<?php

namespace Tourze\JsonRPCCacheBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\JsonRPCCacheBundle\DependencyInjection\JsonRPCCacheExtension;
use Tourze\JsonRPCCacheBundle\EventSubscriber\CacheSubscriber;

class JsonRPCCacheExtensionTest extends TestCase
{
    private JsonRPCCacheExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new JsonRPCCacheExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoad(): void
    {
        $this->extension->load([], $this->container);
        
        // 验证服务是否被加载
        $this->assertTrue($this->container->has(CacheSubscriber::class));
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
        
        // 验证服务是否被配置为自动配置和自动注入
        $definition = $this->container->getDefinition(CacheSubscriber::class);
        $this->assertTrue($definition->isAutoconfigured());
        $this->assertTrue($definition->isAutowired());
    }
}
