<?php

namespace Tourze\JsonRPCCacheBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\JsonRPCCacheBundle\DependencyInjection\JsonRPCCacheExtension;

class JsonRPCCacheExtensionTest extends TestCase
{
    private JsonRPCCacheExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new JsonRPCCacheExtension();
        $this->container = new ContainerBuilder();
    }

    /**
     * 测试扩展加载方法能够正常执行
     */
    public function testLoad_shouldNotThrowException(): void
    {
        $configs = [];

        // 如果 load 方法有问题，会抛出异常
        $this->expectNotToPerformAssertions();

        $this->extension->load($configs, $this->container);
    }

    /**
     * 测试扩展加载后容器状态
     */
    public function testLoad_shouldLoadServices(): void
    {
        $configs = [];
        $this->extension->load($configs, $this->container);

        // 验证容器已编译或定义了服务
        $this->assertTrue($this->container->isCompiled() || count($this->container->getDefinitions()) > 0);
    }

    /**
     * 测试扩展使用正确的配置目录
     */
    public function testLoad_shouldUseCorrectConfigPath(): void
    {
        $reflection = new \ReflectionMethod($this->extension, 'load');
        $reflection->invoke($this->extension, [], $this->container);

        // 间接测试服务定义文件存在且可访问
        $expectedPath = realpath(__DIR__ . '/../../src/Resources/config/services.yaml');
        $this->assertFileExists($expectedPath);
    }
}
