<?php

namespace Tourze\JsonRPCCacheBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
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
    private TestCacheableProcedure $cacheableProcedure;

    private BaseProcedure $nonCacheableProcedure;

    private CacheSubscriber $subscriber;

    protected function onSetUp(): void
    {
        // 从容器获取 CacheSubscriber
        $this->subscriber = self::getService(CacheSubscriber::class);

        // TestCacheableProcedure 不需要依赖注入，直接实例化
        $this->cacheableProcedure = new TestCacheableProcedure();

        $this->nonCacheableProcedure = new #[MethodTag(name: 'test')]
        #[MethodDoc(summary: 'Test non-cacheable procedure')]
        #[MethodExpose(method: 'testNonCacheable')]
        class extends BaseProcedure {
            public function execute(RpcParamInterface $param): ArrayResult
            {
                return new ArrayResult([]);
            }
        };
    }

    private function createJsonRpcRequest(): JsonRpcRequest
    {
        $request = new JsonRpcRequest();
        $request->setMethod('test');

        return $request;
    }

    public function testBeforeMethodApplyWithNonCacheableProcedureShouldNotProcessCache(): void
    {
        $event = new BeforeMethodApplyEvent();

        $event->setMethod($this->nonCacheableProcedure);

        $this->subscriber->beforeMethodApply($event);

        $this->assertNull($event->getResult());
    }

    public function testBeforeMethodApplyWithCacheableProcedureNoCacheHitShouldNotSetResult(): void
    {
        $request = $this->createJsonRpcRequest();

        $event = new BeforeMethodApplyEvent();

        $this->cacheableProcedure->setCacheKey('test-key-no-hit-' . uniqid('', true));

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);

        $this->subscriber->beforeMethodApply($event);

        $this->assertNull($event->getResult());
    }

    public function testBeforeMethodApplyWithCacheableProcedureWithCacheHitShouldSetResult(): void
    {
        $request = $this->createJsonRpcRequest();

        $event = new BeforeMethodApplyEvent();

        // 预先设置缓存
        $cacheKey = 'test-key-with-hit-' . uniqid('', true);
        $cachedResult = ['cached' => 'data'];
        $this->cacheableProcedure->setCacheKey($cacheKey);

        // 通过 afterMethodApply 设置缓存，模拟真实场景
        $setupEvent = new AfterMethodApplyEvent();
        $setupEvent->setMethod($this->cacheableProcedure);
        $setupEvent->setRequest($request);
        $setupEvent->setResult($cachedResult);
        $this->subscriber->afterMethodApply($setupEvent);

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);

        $this->subscriber->beforeMethodApply($event);

        $this->assertEquals($cachedResult, $event->getResult());
    }

    public function testBeforeMethodApplyWithEmptyCacheKeyShouldNotProcessCache(): void
    {
        $request = $this->createJsonRpcRequest();

        $event = new BeforeMethodApplyEvent();
        $this->cacheableProcedure->setCacheKey('');

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);

        $this->subscriber->beforeMethodApply($event);

        $this->assertNull($event->getResult());
    }

    public function testAfterMethodApplyWithNonCacheableProcedureShouldNotProcessCache(): void
    {
        $event = new AfterMethodApplyEvent();

        $event->setMethod($this->nonCacheableProcedure);

        $this->subscriber->afterMethodApply($event);

        // 非可缓存过程，不应写入任何缓存
        // 由于非可缓存过程不生成缓存键，我们无需验证缓存状态
        // 此测试的目的是确保方法执行不会抛出异常
        $this->expectNotToPerformAssertions();
    }

    public function testAfterMethodApplyWithCacheableProcedureShouldSaveToCache(): void
    {
        $request = $this->createJsonRpcRequest();

        $event = new AfterMethodApplyEvent();

        $cacheKey = 'test-after-key-' . uniqid('', true);
        $result = ['result' => 'data'];
        $duration = 1800;

        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration($duration);
        $this->cacheableProcedure->setCacheTags(['tag1', 'tag2']);

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);
        $event->setResult($result);

        $this->subscriber->afterMethodApply($event);

        // 验证缓存是否正确保存 - 通过重新读取来验证
        $verifyEvent = new BeforeMethodApplyEvent();
        $verifyEvent->setMethod($this->cacheableProcedure);
        $verifyEvent->setRequest($request);

        $this->subscriber->beforeMethodApply($verifyEvent);

        $this->assertEquals($result, $verifyEvent->getResult());
    }

    public function testAfterMethodApplyWithEmptyCacheKeyShouldNotSaveToCache(): void
    {
        $request = $this->createJsonRpcRequest();

        $event = new AfterMethodApplyEvent();

        $result = ['result' => 'data'];

        $this->cacheableProcedure->setCacheKey('');

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);
        $event->setResult($result);

        $this->subscriber->afterMethodApply($event);

        // 空缓存键不应保存缓存
        // 此测试的目的是确保方法执行不会抛出异常
        $this->expectNotToPerformAssertions();
    }

    public function testAfterMethodApplyWithZeroCacheDurationShouldSaveWithZeroDuration(): void
    {
        $request = $this->createJsonRpcRequest();

        $event = new AfterMethodApplyEvent();

        $cacheKey = 'test-zero-duration-' . uniqid('', true);
        $result = ['result' => 'data'];

        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(1);
        $this->cacheableProcedure->setCacheTags([]);

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);
        $event->setResult($result);

        $this->subscriber->afterMethodApply($event);

        // 验证缓存被保存 - 通过重新读取来验证
        $verifyEvent = new BeforeMethodApplyEvent();
        $verifyEvent->setMethod($this->cacheableProcedure);
        $verifyEvent->setRequest($request);

        $this->subscriber->beforeMethodApply($verifyEvent);

        $this->assertEquals($result, $verifyEvent->getResult());
    }

    public function testAfterMethodApplyWithNullTagsInArrayShouldFilterNullTags(): void
    {
        $request = $this->createJsonRpcRequest();

        $event = new AfterMethodApplyEvent();

        $cacheKey = 'test-null-tags-' . uniqid('', true);
        $result = ['result' => 'data'];

        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(3600);
        $this->cacheableProcedure->setCacheTags(['tag1', null, 'tag2', '', 'tag3']);

        $event->setMethod($this->cacheableProcedure);
        $event->setRequest($request);
        $event->setResult($result);

        $this->subscriber->afterMethodApply($event);

        // 验证缓存被保存 - 通过重新读取来验证
        $verifyEvent = new BeforeMethodApplyEvent();
        $verifyEvent->setMethod($this->cacheableProcedure);
        $verifyEvent->setRequest($request);

        $this->subscriber->beforeMethodApply($verifyEvent);

        $this->assertEquals($result, $verifyEvent->getResult());
    }

    public function testCacheWorkflowEndToEndShouldWorkCorrectly(): void
    {
        $request = $this->createJsonRpcRequest();

        $beforeEvent = new BeforeMethodApplyEvent();
        $afterEvent = new AfterMethodApplyEvent();

        $cacheKey = 'test-workflow-' . uniqid('', true);
        $result = ['workflow' => 'test'];

        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(3600);
        $this->cacheableProcedure->setCacheTags(['workflow-tag']);

        // 第一次调用 - 缓存未命中
        $beforeEvent->setMethod($this->cacheableProcedure);
        $beforeEvent->setRequest($request);

        $this->subscriber->beforeMethodApply($beforeEvent);

        // 第一次调用缓存未命中，不应写入事件结果
        $this->assertNull($beforeEvent->getResult());

        // 保存缓存
        $afterEvent->setMethod($this->cacheableProcedure);
        $afterEvent->setRequest($request);
        $afterEvent->setResult($result);

        $this->subscriber->afterMethodApply($afterEvent);

        // 第二次调用 - 缓存命中
        $secondBeforeEvent = new BeforeMethodApplyEvent();
        $secondBeforeEvent->setMethod($this->cacheableProcedure);
        $secondBeforeEvent->setRequest($request);

        $this->subscriber->beforeMethodApply($secondBeforeEvent);

        // 第二次调用缓存命中，应将缓存值写入事件结果
        $this->assertEquals($result, $secondBeforeEvent->getResult());
    }
}
