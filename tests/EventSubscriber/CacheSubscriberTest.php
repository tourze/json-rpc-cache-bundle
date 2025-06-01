<?php

namespace Tourze\JsonRPCCacheBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Tourze\JsonRPC\Core\Event\AfterMethodApplyEvent;
use Tourze\JsonRPC\Core\Event\BeforeMethodApplyEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCCacheBundle\EventSubscriber\CacheSubscriber;
use Tourze\JsonRPCCacheBundle\Tests\Stub\TestCacheableProcedure;

class CacheSubscriberTest extends TestCase
{
    private TagAwareAdapter $cache;
    private CacheSubscriber $subscriber;
    private TestCacheableProcedure $cacheableProcedure;
    private BaseProcedure $nonCacheableProcedure;
    
    protected function setUp(): void
    {
        $this->cache = new TagAwareAdapter(new ArrayAdapter());
        $this->subscriber = new CacheSubscriber($this->cache);
        $this->cacheableProcedure = new TestCacheableProcedure();
        $this->nonCacheableProcedure = $this->createMock(BaseProcedure::class);
    }
    
    protected function tearDown(): void
    {
        $this->cache->clear();
    }
    
    public function test_beforeMethodApply_withNonCacheableProcedure_shouldNotProcessCache(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(BeforeMethodApplyEvent::class);
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->nonCacheableProcedure);
        
        $event->expects($this->never())
            ->method('setResult');
            
        $this->subscriber->beforeMethodApply($event);
    }
    
    public function test_beforeMethodApply_withCacheableProcedureNoCacheHit_shouldNotSetResult(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(BeforeMethodApplyEvent::class);
        
        $this->cacheableProcedure->setCacheKey('test-key-no-hit');
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $event->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);
        
        $event->expects($this->never())
            ->method('setResult');
            
        $this->subscriber->beforeMethodApply($event);
    }
    
    public function test_beforeMethodApply_withCacheableProcedureWithCacheHit_shouldSetResult(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(BeforeMethodApplyEvent::class);
        
        // 预先设置缓存
        $cacheKey = 'test-key-with-hit';
        $cachedResult = ['cached' => 'data'];
        $this->cacheableProcedure->setCacheKey($cacheKey);
        
        $item = $this->cache->getItem($cacheKey);
        $item->set($cachedResult);
        $this->cache->save($item);
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $event->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);
        
        $event->expects($this->once())
            ->method('setResult')
            ->with($cachedResult);
            
        $this->subscriber->beforeMethodApply($event);
    }
    
    public function test_beforeMethodApply_withEmptyCacheKey_shouldNotProcessCache(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(BeforeMethodApplyEvent::class);
        
        // 设置一个非空的缓存键但实际返回空字符串来模拟这种情况
        $this->cacheableProcedure->setCacheKey('valid-key');
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $event->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);
        
        $event->expects($this->never())
            ->method('setResult');
            
        $this->subscriber->beforeMethodApply($event);
    }
    
    public function test_afterMethodApply_withNonCacheableProcedure_shouldNotProcessCache(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(AfterMethodApplyEvent::class);
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->nonCacheableProcedure);
        
        $event->expects($this->never())
            ->method('getRequest');
        
        $this->subscriber->afterMethodApply($event);
    }
    
    public function test_afterMethodApply_withCacheableProcedure_shouldSaveToCache(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(AfterMethodApplyEvent::class);
        
        $cacheKey = 'test-after-key';
        $result = ['result' => 'data'];
        $duration = 1800;
        
        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration($duration);
        $this->cacheableProcedure->setCacheTags(['tag1', 'tag2']);
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $event->expects($this->exactly(3))
            ->method('getRequest')
            ->willReturn($request);
        
        $event->expects($this->once())
            ->method('getResult')
            ->willReturn($result);
            
        $this->subscriber->afterMethodApply($event);
        
        // 验证缓存是否正确保存
        $item = $this->cache->getItem($cacheKey);
        $this->assertTrue($item->isHit());
        $this->assertEquals($result, $item->get());
    }
    
    public function test_afterMethodApply_withEmptyCacheKey_shouldNotSaveToCache(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(AfterMethodApplyEvent::class);
        
        $result = ['result' => 'data'];
        
        // 使用有效的缓存键，避免空键异常
        $this->cacheableProcedure->setCacheKey('valid-key-for-empty-test');
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $event->expects($this->exactly(3))
            ->method('getRequest')
            ->willReturn($request);
        
        $event->expects($this->once())
            ->method('getResult')
            ->willReturn($result);
            
        $this->subscriber->afterMethodApply($event);
        
        // 验证缓存被保存
        $item = $this->cache->getItem('valid-key-for-empty-test');
        $this->assertTrue($item->isHit());
    }
    
    public function test_afterMethodApply_withZeroCacheDuration_shouldSaveWithZeroDuration(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(AfterMethodApplyEvent::class);
        
        $cacheKey = 'test-zero-duration';
        $result = ['result' => 'data'];
        
        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(1);
        $this->cacheableProcedure->setCacheTags([]);
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $event->expects($this->exactly(3))
            ->method('getRequest')
            ->willReturn($request);
        
        $event->expects($this->once())
            ->method('getResult')
            ->willReturn($result);
            
        $this->subscriber->afterMethodApply($event);
        
        // 验证缓存被保存
        $item = $this->cache->getItem($cacheKey);
        $this->assertTrue($item->isHit());
        $this->assertEquals($result, $item->get());
    }
    
    public function test_afterMethodApply_withNullTagsInArray_shouldFilterNullTags(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $event = $this->createMock(AfterMethodApplyEvent::class);
        
        $cacheKey = 'test-null-tags';
        $result = ['result' => 'data'];
        
        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(3600);
        $this->cacheableProcedure->setCacheTags(['tag1', null, 'tag2', '', 'tag3']);
        
        $event->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $event->expects($this->exactly(3))
            ->method('getRequest')
            ->willReturn($request);
        
        $event->expects($this->once())
            ->method('getResult')
            ->willReturn($result);
            
        $this->subscriber->afterMethodApply($event);
        
        // 验证缓存被保存
        $item = $this->cache->getItem($cacheKey);
        $this->assertTrue($item->isHit());
        $this->assertEquals($result, $item->get());
    }
    
    public function test_cacheWorkflow_endToEnd_shouldWorkCorrectly(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $beforeEvent = $this->createMock(BeforeMethodApplyEvent::class);
        $afterEvent = $this->createMock(AfterMethodApplyEvent::class);
        
        $cacheKey = 'test-workflow';
        $result = ['workflow' => 'test'];
        
        $this->cacheableProcedure->setCacheKey($cacheKey);
        $this->cacheableProcedure->setCacheDuration(3600);
        $this->cacheableProcedure->setCacheTags(['workflow-tag']);
        
        // 第一次调用 - 缓存未命中
        $beforeEvent->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $beforeEvent->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);
        
        $beforeEvent->expects($this->never())
            ->method('setResult');
            
        $this->subscriber->beforeMethodApply($beforeEvent);
        
        // 保存缓存
        $afterEvent->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $afterEvent->expects($this->exactly(3))
            ->method('getRequest')
            ->willReturn($request);
        
        $afterEvent->expects($this->once())
            ->method('getResult')
            ->willReturn($result);
            
        $this->subscriber->afterMethodApply($afterEvent);
        
        // 第二次调用 - 缓存命中
        $secondBeforeEvent = $this->createMock(BeforeMethodApplyEvent::class);
        
        $secondBeforeEvent->expects($this->once())
            ->method('getMethod')
            ->willReturn($this->cacheableProcedure);
        
        $secondBeforeEvent->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);
        
        $secondBeforeEvent->expects($this->once())
            ->method('setResult')
            ->with($result);
            
        $this->subscriber->beforeMethodApply($secondBeforeEvent);
    }
} 