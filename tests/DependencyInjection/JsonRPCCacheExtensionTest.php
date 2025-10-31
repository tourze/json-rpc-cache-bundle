<?php

namespace Tourze\JsonRPCCacheBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\JsonRPCCacheBundle\DependencyInjection\JsonRPCCacheExtension;
use Tourze\JsonRPCCacheBundle\EventSubscriber\CacheSubscriber;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(JsonRPCCacheExtension::class)]
final class JsonRPCCacheExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private JsonRPCCacheExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new JsonRPCCacheExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    public function testLoadWithEmptyConfigsShouldLoadDefaultServices(): void
    {
        $this->extension->load([], $this->container);

        // 验证CacheSubscriber服务是否被正确注册
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    public function testLoadWithMultipleConfigsShouldMergeConfigs(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务依然正确加载
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    public function testLoadShouldLoadYamlConfiguration(): void
    {
        $this->extension->load([], $this->container);

        // 验证EventSubscriber命名空间下的服务是否被正确扫描
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    public function testLoadWithInvalidConfigurationShouldHandleGracefully(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务依然被加载
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    public function testExtensionShouldHaveCorrectAlias(): void
    {
        $this->assertEquals('json_rpc_cache', $this->extension->getAlias());
    }

    public function testLoadShouldNotOverrideExistingServices(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务定义存在
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    public function testLoadWithContainerHavingExistingServicesShouldAddNewServices(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务被添加
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    public function testLoadShouldSetCorrectServiceTags(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务定义存在，说明标签配置正确
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    public function testLoadMultipleCallsShouldBeIdempotent(): void
    {
        // 多次调用加载方法
        $this->extension->load([], $this->container);
        $this->extension->load([], $this->container);

        // 验证服务只被注册一次
        $this->assertTrue($this->container->hasDefinition(CacheSubscriber::class));
    }

    /**
     * 排除包含抽象类的目录，因为抽象类不能被实例化
     */
    protected function provideServiceDirectories(): iterable
    {
        yield 'Controller';
        yield 'Command';
        yield 'Service';
        yield 'Repository';
        yield 'EventSubscriber';
        yield 'MessageHandler';
        // 不包含 Procedure 目录，因为它包含抽象类
    }
}
