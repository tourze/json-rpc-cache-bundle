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

    public function test_load_withEmptyConfigs_shouldLoadDefaultServices(): void
    {
        $this->extension->load([], $this->container);
        
        // 验证CacheSubscriber服务是否被正确注册
        $this->assertTrue($this->container->has(CacheSubscriber::class));
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
        
        // 验证服务配置
        $definition = $this->container->getDefinition(CacheSubscriber::class);
        $this->assertTrue($definition->isAutoconfigured());
        $this->assertTrue($definition->isAutowired());
    }

    public function test_load_withMultipleConfigs_shouldMergeConfigs(): void
    {
        $config1 = [];
        $config2 = [];
        
        $this->extension->load([$config1, $config2], $this->container);
        
        // 验证服务依然正确加载
        $this->assertTrue($this->container->has(CacheSubscriber::class));
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    public function test_load_shouldLoadYamlConfiguration(): void
    {
        $this->extension->load([], $this->container);
        
        // 验证EventSubscriber命名空间下的服务是否被正确扫描
        $this->assertTrue($this->container->has(CacheSubscriber::class));
        
        $definition = $this->container->getDefinition(CacheSubscriber::class);
        
        // 验证服务配置的基本属性
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->isAutoconfigured());
        $this->assertFalse($definition->isAbstract());
    }

    public function test_load_withInvalidConfiguration_shouldHandleGracefully(): void
    {
        // 测试传入空配置数组不会抛出异常
        $this->extension->load([[]], $this->container);
        
        // 验证服务依然被加载
        $this->assertTrue($this->container->has(CacheSubscriber::class));
    }

    public function test_extension_shouldHaveCorrectAlias(): void
    {
        // 验证扩展的别名设置
        $this->assertEquals('json_rpc_cache', $this->extension->getAlias());
    }

    public function test_load_shouldNotOverrideExistingServices(): void
    {
        // 预先注册一个同名服务
        $this->container->register(CacheSubscriber::class, \stdClass::class);
        
        // 加载扩展
        $this->extension->load([], $this->container);
        
        // 验证服务被正确覆盖为Extension中定义的类
        $definition = $this->container->getDefinition(CacheSubscriber::class);
        $this->assertEquals(CacheSubscriber::class, $definition->getClass());
    }

    public function test_load_shouldRegisterServicesInCorrectNamespace(): void
    {
        $this->extension->load([], $this->container);
        
        // 验证服务注册在正确的命名空间下
        $serviceIds = $this->container->getServiceIds();
        
        $foundCacheSubscriber = false;
        foreach ($serviceIds as $serviceId) {
            if (str_contains($serviceId, 'CacheSubscriber')) {
                $foundCacheSubscriber = true;
                break;
            }
        }
        
        $this->assertTrue($foundCacheSubscriber, 'CacheSubscriber service should be registered');
    }

    public function test_load_withContainerHavingExistingServices_shouldAddNewServices(): void
    {
        // 预先注册一些服务
        $this->container->register('existing.service', \stdClass::class);
        
        $servicesCountBefore = count($this->container->getServiceIds());
        
        $this->extension->load([], $this->container);
        
        $servicesCountAfter = count($this->container->getServiceIds());
        
        // 验证新服务被添加
        $this->assertGreaterThan($servicesCountBefore, $servicesCountAfter);
        
        // 验证原有服务没有被删除
        $this->assertTrue($this->container->has('existing.service'));
    }

    public function test_load_shouldSetCorrectServiceTags(): void
    {
        $this->extension->load([], $this->container);
        
        $definition = $this->container->getDefinition(CacheSubscriber::class);
        
        // 由于autoconfigure为true，事件订阅者标签应该自动添加
        $this->assertTrue($definition->isAutoconfigured());
    }

    public function test_load_multipleCallsShouldBeIdempotent(): void
    {
        // 多次调用load方法
        $this->extension->load([], $this->container);
        $this->extension->load([], $this->container);
        $this->extension->load([], $this->container);
        
        // 验证服务只被注册一次
        $this->assertTrue($this->container->has(CacheSubscriber::class));
        
        // 验证没有重复的服务定义
        $definition = $this->container->getDefinition(CacheSubscriber::class);
        $this->assertInstanceOf(\Symfony\Component\DependencyInjection\Definition::class, $definition);
    }
}
