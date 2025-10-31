<?php

namespace Tourze\JsonRPCCacheBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Event\AfterMethodApplyEvent;
use Tourze\JsonRPC\Core\Event\BeforeMethodApplyEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCCacheBundle\EventSubscriber\CacheSubscriber;
use Tourze\JsonRPCCacheBundle\Tests\TestCacheableProcedure;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(CacheSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class CacheSubscriberTest extends AbstractEventSubscriberTestCase
{
    private TagAwareAdapter $cache;

    private TestCacheableProcedure $cacheableProcedure;

    private BaseProcedure $nonCacheableProcedure;

    private CacheSubscriber $subscriber;

    protected function onSetUp(): void
    {
        $this->cache = new TagAwareAdapter(new ArrayAdapter());
        $this->cacheableProcedure = new TestCacheableProcedure();
        $this->nonCacheableProcedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: 'Test non-cacheable procedure')]
        #[MethodExpose(method: 'testNonCacheable')]
        class extends BaseProcedure {
            public function execute(): array
            {
                return [];
            }
        };

        // 为了测试目的，直接实例化CacheSubscriber以使用我们的测试缓存实例
        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        $this->subscriber = new CacheSubscriber($this->cache, new NullLogger());
    }

    public function testBeforeMethodApplyWithNonCacheableProcedureShouldNotProcessCache(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends BeforeMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private bool $setResultCalled = false;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function setResult(mixed $result): void
            {
                $this->setResultCalled = true;
            }

            public function wasSetResultCalled(): bool
            {
                return $this->setResultCalled;
            }
        };

        $event->setMethod($this->nonCacheableProcedure);

        $this->subscriber->beforeMethodApply($event);

        // 验证setResult从未被调用
        $this->assertFalse($event->wasSetResultCalled());
    }

    public function testBeforeMethodApplyWithCacheableProcedureNoCacheHitShouldNotSetResult(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends BeforeMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private bool $setResultCalled = false;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function setResult(mixed $result): void
            {
                $this->setResultCalled = true;
            }

            public function wasSetResultCalled(): bool
            {
                return $this->setResultCalled;
            }
        };

        $this->cacheableProcedure->setCacheKey('test-key-no-hit');

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);

        $this->subscriber->beforeMethodApply($event);

        // 验证setResult从未被调用
        $this->assertFalse($event->wasSetResultCalled());
    }

    public function testBeforeMethodApplyWithCacheableProcedureWithCacheHitShouldSetResult(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends BeforeMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private bool $setResultCalled = false;

            private mixed $setResultValue = null;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function setResult(mixed $result): void
            {
                $this->setResultCalled = true;
                $this->setResultValue = $result;
            }

            public function wasSetResultCalled(): bool
            {
                return $this->setResultCalled;
            }

            public function getSetResultValue(): mixed
            {
                return $this->setResultValue;
            }
        };

        // 预先设置缓存
        $cacheKey = 'test-key-with-hit';
        $cachedResult = ['cached' => 'data'];
        $this->cacheableProcedure->setCacheKey($cacheKey);

        $item = $this->cache->getItem($cacheKey);
        $item->set($cachedResult);
        $this->cache->save($item);

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);

        $this->subscriber->beforeMethodApply($event);

        // 验证setResult被调用且传入了正确的缓存结果
        $this->assertTrue($event->wasSetResultCalled());
        $this->assertEquals($cachedResult, $event->getSetResultValue());
    }

    public function testBeforeMethodApplyWithEmptyCacheKeyShouldNotProcessCache(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends BeforeMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private bool $setResultCalled = false;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function setResult(mixed $result): void
            {
                $this->setResultCalled = true;
            }

            public function wasSetResultCalled(): bool
            {
                return $this->setResultCalled;
            }
        };

        // 设置一个非空的缓存键但实际返回空字符串来模拟这种情况
        $this->cacheableProcedure->setCacheKey('valid-key');

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);

        $this->subscriber->beforeMethodApply($event);

        // 验证setResult从未被调用
        $this->assertFalse($event->wasSetResultCalled());
    }

    public function testAfterMethodApplyWithNonCacheableProcedureShouldNotProcessCache(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends AfterMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private int $getRequestCallCount = 0;

            private ?JsonRpcRequest $request = null;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                ++$this->getRequestCallCount;

                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequestForTest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function getRequestCallCount(): int
            {
                return $this->getRequestCallCount;
            }
        };

        $event->setMethod($this->nonCacheableProcedure);

        $this->subscriber->afterMethodApply($event);

        // 验证getRequest从未被调用
        $this->assertEquals(0, $event->getRequestCallCount());
    }

    public function testAfterMethodApplyWithCacheableProcedureShouldSaveToCache(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends AfterMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private mixed $result = null;

            private int $getRequestCallCount = 0;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                ++$this->getRequestCallCount;

                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function getResult(): mixed
            {
                return $this->result;
            }

            public function setResult(mixed $result): void
            {
                $this->result = $result;
            }

            public function getRequestCallCount(): int
            {
                return $this->getRequestCallCount;
            }
        };

        $cacheKey = 'test-after-key';
        $result = ['result' => 'data'];
        $duration = 1800;

        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration($duration);
        $this->cacheableProcedure->setCacheTags(['tag1', 'tag2']);

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);
        $event->setResult($result);

        $this->subscriber->afterMethodApply($event);

        // 验证getRequest被调用了3次
        $this->assertEquals(3, $event->getRequestCallCount());

        // 验证缓存是否正确保存
        $item = $this->cache->getItem($cacheKey);
        $this->assertTrue($item->isHit());
        $this->assertEquals($result, $item->get());
    }

    public function testAfterMethodApplyWithEmptyCacheKeyShouldNotSaveToCache(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends AfterMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private mixed $result = null;

            private int $getRequestCallCount = 0;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                ++$this->getRequestCallCount;

                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function getResult(): mixed
            {
                return $this->result;
            }

            public function setResult(mixed $result): void
            {
                $this->result = $result;
            }

            public function getRequestCallCount(): int
            {
                return $this->getRequestCallCount;
            }
        };

        $result = ['result' => 'data'];

        // 使用有效的缓存键，避免空键异常
        $this->cacheableProcedure->setCacheKey('valid-key-for-empty-test');

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);
        $event->setResult($result);

        $this->subscriber->afterMethodApply($event);

        // 验证getRequest被调用了3次
        $this->assertEquals(3, $event->getRequestCallCount());

        // 验证缓存被保存
        $item = $this->cache->getItem('valid-key-for-empty-test');
        $this->assertTrue($item->isHit());
    }

    public function testAfterMethodApplyWithZeroCacheDurationShouldSaveWithZeroDuration(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends AfterMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private mixed $result = null;

            private int $getRequestCallCount = 0;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                ++$this->getRequestCallCount;

                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function getResult(): mixed
            {
                return $this->result;
            }

            public function setResult(mixed $result): void
            {
                $this->result = $result;
            }

            public function getRequestCallCount(): int
            {
                return $this->getRequestCallCount;
            }
        };

        $cacheKey = 'test-zero-duration';
        $result = ['result' => 'data'];

        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(1);
        $this->cacheableProcedure->setCacheTags([]);

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);
        $event->setResult($result);

        $this->subscriber->afterMethodApply($event);

        // 验证getRequest被调用了3次
        $this->assertEquals(3, $event->getRequestCallCount());

        // 验证缓存被保存
        $item = $this->cache->getItem($cacheKey);
        $this->assertTrue($item->isHit());
        $this->assertEquals($result, $item->get());
    }

    public function testAfterMethodApplyWithNullTagsInArrayShouldFilterNullTags(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $event = new class extends AfterMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private mixed $result = null;

            private int $getRequestCallCount = 0;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                ++$this->getRequestCallCount;

                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function getResult(): mixed
            {
                return $this->result;
            }

            public function setResult(mixed $result): void
            {
                $this->result = $result;
            }

            public function getRequestCallCount(): int
            {
                return $this->getRequestCallCount;
            }
        };

        $cacheKey = 'test-null-tags';
        $result = ['result' => 'data'];

        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(3600);
        $this->cacheableProcedure->setCacheTags(['tag1', null, 'tag2', '', 'tag3']);

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);
        $event->setResult($result);

        $this->subscriber->afterMethodApply($event);

        // 验证getRequest被调用了3次
        $this->assertEquals(3, $event->getRequestCallCount());

        // 验证缓存被保存
        $item = $this->cache->getItem($cacheKey);
        $this->assertTrue($item->isHit());
        $this->assertEquals($result, $item->get());
    }

    public function testCacheWorkflowEndToEndShouldWorkCorrectly(): void
    {
        $request = new class extends JsonRpcRequest {
            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }
        };

        $beforeEvent = new class extends BeforeMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private bool $setResultCalled = false;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function setResult(mixed $result): void
            {
                $this->setResultCalled = true;
            }

            public function wasSetResultCalled(): bool
            {
                return $this->setResultCalled;
            }
        };

        $afterEvent = new class extends AfterMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private mixed $result = null;

            private int $getRequestCallCount = 0;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                ++$this->getRequestCallCount;

                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function getResult(): mixed
            {
                return $this->result;
            }

            public function setResult(mixed $result): void
            {
                $this->result = $result;
            }

            public function getRequestCallCount(): int
            {
                return $this->getRequestCallCount;
            }
        };

        $cacheKey = 'test-workflow';
        $result = ['workflow' => 'test'];

        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(3600);
        $this->cacheableProcedure->setCacheTags(['workflow-tag']);

        // 第一次调用 - 缓存未命中
        $beforeEvent->setMethod($this->cacheableProcedure);
        $beforeEvent->setRequest($request);

        $this->subscriber->beforeMethodApply($beforeEvent);

        // 验证setResult从未被调用
        $this->assertFalse($beforeEvent->wasSetResultCalled());

        // 保存缓存
        $afterEvent->setMethod($this->cacheableProcedure);
        $afterEvent->setRequest($request);
        $afterEvent->setResult($result);

        $this->subscriber->afterMethodApply($afterEvent);

        // 验证getRequest被调用了3次
        $this->assertEquals(3, $afterEvent->getRequestCallCount());

        // 第二次调用 - 缓存命中
        $secondBeforeEvent = new class extends BeforeMethodApplyEvent {
            private ?JsonRpcMethodInterface $method = null;

            private ?JsonRpcRequest $request = null;

            private bool $setResultCalled = false;

            private mixed $setResultValue = null;

            public function __construct()
            {
                // 空构造函数，避免父类构造参数
            }

            public function getMethod(): JsonRpcMethodInterface
            {
                return $this->method ?? throw new \LogicException('Method not set');
            }

            public function setMethod(JsonRpcMethodInterface $method): void
            {
                $this->method = $method;
            }

            public function getRequest(): JsonRpcRequest
            {
                return $this->request ?? throw new \LogicException('Request not set');
            }

            public function setRequest(JsonRpcRequest $request): void
            {
                $this->request = $request;
            }

            public function setResult(mixed $result): void
            {
                $this->setResultCalled = true;
                $this->setResultValue = $result;
            }

            public function wasSetResultCalled(): bool
            {
                return $this->setResultCalled;
            }

            public function getSetResultValue(): mixed
            {
                return $this->setResultValue;
            }
        };

        $secondBeforeEvent->setMethod($this->cacheableProcedure);
        $secondBeforeEvent->setRequest($request);

        $this->subscriber->beforeMethodApply($secondBeforeEvent);

        // 验证setResult被调用且传入了正确的缓存结果
        $this->assertTrue($secondBeforeEvent->wasSetResultCalled());
        $this->assertEquals($result, $secondBeforeEvent->getSetResultValue());
    }
}
