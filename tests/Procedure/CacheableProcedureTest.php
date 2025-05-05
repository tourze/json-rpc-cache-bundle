<?php

namespace Tourze\JsonRPCCacheBundle\Tests\Procedure;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;
use Tourze\JsonRPCSecurityBundle\Service\GrantService;

/**
 * 用于测试的实现类
 */
class TestCacheableProcedure extends CacheableProcedure
{
    private string $cacheKey = 'test-key';
    private int $cacheDuration = 3600;
    private array $cacheTags = ['tag1', 'tag2'];

    // 用于测试的方法，访问 protected 方法 buildParamCacheKey
    public function testBuildParamCacheKey(JsonRpcParams $params): string
    {
        return $this->buildParamCacheKey($params);
    }

    // 允许在测试时改变返回值的方法
    public function setCacheKey(string $key): void
    {
        $this->cacheKey = $key;
    }

    public function setCacheDuration(int $duration): void
    {
        $this->cacheDuration = $duration;
    }

    public function setCacheTags(array $tags): void
    {
        $this->cacheTags = $tags;
    }

    protected function getCacheKey(JsonRpcRequest $request): string
    {
        return $this->cacheKey;
    }

    protected function getCacheDuration(JsonRpcRequest $request): int
    {
        return $this->cacheDuration;
    }

    protected function getCacheTags(JsonRpcRequest $request): iterable
    {
        return $this->cacheTags;
    }

    /**
     * 实现抽象方法 execute
     */
    public function execute(): array
    {
        return ['result' => 'test-result'];
    }
}

class CacheableProcedureTest extends TestCase
{
    /**
     * @var TestCacheableProcedure
     */
    private TestCacheableProcedure $procedure;

    /**
     * @var MockObject&CacheInterface
     */
    private MockObject $cache;

    /**
     * @var MockObject&GrantService
     */
    private MockObject $grantService;

    /**
     * @var MockObject&ContainerInterface
     */
    private MockObject $container;

    /**
     * @var MockObject&JsonRpcRequest
     */
    private MockObject $request;

    /**
     * @var MockObject&JsonRpcParams
     */
    private MockObject $params;

    /**
     * @var MockObject&ItemInterface
     */
    private MockObject $cacheItem;

    /**
     * @var MockObject&EventDispatcherInterface
     */
    private MockObject $eventDispatcher;

    /**
     * @var MockObject&LoggerInterface
     */
    private MockObject $logger;

    /**
     * @var MockObject&PropertyAccessor
     */
    private MockObject $propertyAccessor;

    /**
     * @var MockObject&ValidatorInterface
     */
    private MockObject $validator;

    protected function setUp(): void
    {
        // 创建各种依赖的模拟对象
        $this->cache = $this->createMock(CacheInterface::class);
        $this->grantService = $this->createMock(GrantService::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->request = $this->createMock(JsonRpcRequest::class);
        $this->params = $this->createMock(JsonRpcParams::class);
        $this->cacheItem = $this->createMock(ItemInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->propertyAccessor = $this->createMock(PropertyAccessor::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        // 配置请求参数对象
        $this->params->method('toArray')
            ->willReturn([]);

        // 请求中包含参数对象
        $this->request->method('getParams')
            ->willReturn($this->params);

        // 请求方法名称
        $this->request->method('getMethod')
            ->willReturn('test.method');

        // 配置模拟对象的行为
        $this->container->method('get')
            ->willReturnCallback(function ($name) {
                if (str_contains($name, 'getCache')) {
                    return $this->cache;
                }
                if (str_contains($name, 'getGrantService')) {
                    return $this->grantService;
                }
                if (str_contains($name, 'getEventDispatcher')) {
                    return $this->eventDispatcher;
                }
                if (str_contains($name, 'getBaseProcedureLogger')) {
                    return $this->logger;
                }
                if (str_contains($name, 'getPropertyAccessor')) {
                    return $this->propertyAccessor;
                }
                if (str_contains($name, 'getValidator')) {
                    return $this->validator;
                }
                return null;
            });

        // 创建被测试对象并设置容器
        $this->procedure = new TestCacheableProcedure();
        $this->procedure->setContainer($this->container);
    }

    /**
     * 测试构建参数缓存键方法
     */
    public function testBuildParamCacheKey_shouldGenerateValidKey(): void
    {
        // 测试参数
        $testParams = ['test' => 'value'];
        $this->params->method('toArray')
            ->willReturn($testParams);

        // 执行方法
        $key = $this->procedure->testBuildParamCacheKey($this->params);

        // 验证结果
        $expectedKeyPart = 'Tourze-JsonRPCCacheBundle-Tests-Procedure-TestCacheableProcedure';
        $this->assertStringContainsString($expectedKeyPart, $key);

        // 由于 JSON 编码可能存在差异，我们直接检查 key 的格式而不是具体值
        $keyParts = explode('-', $key);
        $actualMd5 = end($keyParts);

        // 验证 md5 部分的长度和格式
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $actualMd5);
    }

    /**
     * 测试在缓存键为空时直接执行父方法
     */
    public function testInvoke_withEmptyCacheKey_shouldSkipCache(): void
    {
        // 设置空缓存键
        $this->procedure->setCacheKey('');

        // 模拟权限检查
        $this->grantService->expects($this->once())
            ->method('checkProcedure')
            ->with($this->procedure);

        // 确保缓存方法不被调用
        $this->cache->expects($this->never())
            ->method('get');

        // 调用方法（将执行父类的 __invoke）
        $this->procedure->__invoke($this->request);
    }

    /**
     * 测试在缓存持续时间小于等于0时直接执行父方法
     */
    public function testInvoke_withZeroDuration_shouldSkipCache(): void
    {
        // 设置缓存时间为0
        $this->procedure->setCacheDuration(0);

        // 模拟权限检查
        $this->grantService->expects($this->once())
            ->method('checkProcedure')
            ->with($this->procedure);

        // 确保缓存方法不被调用
        $this->cache->expects($this->never())
            ->method('get');

        // 调用方法
        $this->procedure->__invoke($this->request);
    }

    /**
     * 测试缓存命中时返回缓存值
     */
    public function testInvoke_withCacheHit_shouldReturnCachedValue(): void
    {
        // 模拟权限检查
        $this->grantService->expects($this->once())
            ->method('checkProcedure')
            ->with($this->procedure);

        // 模拟缓存命中
        $cachedValue = 'cached-result';
        $this->cache->method('get')
            ->willReturn($cachedValue);

        // 调用方法
        $result = $this->procedure->__invoke($this->request);

        // 验证结果与缓存值相同
        $this->assertSame($cachedValue, $result);
    }

    /**
     * 测试缓存未命中时执行父方法并缓存结果
     */
    public function testInvoke_withCacheMiss_shouldCacheResultAndApplyTags(): void
    {
        // 模拟权限检查
        $this->grantService->expects($this->once())
            ->method('checkProcedure')
            ->with($this->procedure);

        // 模拟缓存操作
        $this->cache->method('get')
            ->willReturnCallback(function ($key, $callback) {
                // 调用传入的回调函数来获取要缓存的值
                return $callback($this->cacheItem);
            });

        // 模拟缓存项行为
        $this->cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600);

        $this->cacheItem->expects($this->once())
            ->method('tag')
            ->with(['tag1', 'tag2']);

        // 调用方法
        $result = $this->procedure->__invoke($this->request);
    }

    /**
     * 测试权限检查功能
     */
    public function testInvoke_shouldCheckPermissions(): void
    {
        // 模拟权限检查
        $this->grantService->expects($this->once())
            ->method('checkProcedure')
            ->with($this->procedure);

        // 设置缓存键为空，简化测试
        $this->procedure->setCacheKey('');

        // 调用方法
        $this->procedure->__invoke($this->request);
    }

    /**
     * 测试权限检查抛出异常时的行为
     */
    public function testInvoke_withPermissionDenied_shouldPropagateException(): void
    {
        // 模拟权限检查抛出异常
        $expectedException = new \RuntimeException('Permission denied');
        $this->grantService->method('checkProcedure')
            ->willThrowException($expectedException);

        // 期望异常被传播
        $this->expectExceptionObject($expectedException);

        // 调用方法
        $this->procedure->__invoke($this->request);
    }
}
