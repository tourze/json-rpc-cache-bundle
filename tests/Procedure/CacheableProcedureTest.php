<?php

namespace Tourze\JsonRPCCacheBundle\Tests\Procedure;

use PHPUnit\Framework\TestCase;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Tests\Stub\TestCacheableProcedure;
use Yiisoft\Json\Json;

class CacheableProcedureTest extends TestCase
{
    private TestCacheableProcedure $procedure;
    
    protected function setUp(): void
    {
        $this->procedure = new TestCacheableProcedure();
    }
    
    public function testBuildParamCacheKey_WithEmptyParams(): void
    {
        $params = $this->createMock(JsonRpcParams::class);
        $params->expects($this->once())
            ->method('toArray')
            ->willReturn([]);
            
        // 使用反射访问protected方法
        $reflection = new \ReflectionClass(TestCacheableProcedure::class);
        $method = $reflection->getMethod('buildParamCacheKey');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->procedure, $params);
        
        $expectedPrefix = str_replace('\\', '-', TestCacheableProcedure::class);
        $expectedSuffix = md5(Json::encode([]));
        $expected = $expectedPrefix . '-' . $expectedSuffix;
        
        $this->assertEquals($expected, $result);
    }
    
    public function testBuildParamCacheKey_WithSimpleParams(): void
    {
        $paramsArray = ['id' => 123, 'name' => 'test'];
        $params = $this->createMock(JsonRpcParams::class);
        $params->expects($this->once())
            ->method('toArray')
            ->willReturn($paramsArray);
            
        // 使用反射访问protected方法
        $reflection = new \ReflectionClass(TestCacheableProcedure::class);
        $method = $reflection->getMethod('buildParamCacheKey');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->procedure, $params);
        
        $expectedPrefix = str_replace('\\', '-', TestCacheableProcedure::class);
        $expectedSuffix = md5(Json::encode($paramsArray));
        $expected = $expectedPrefix . '-' . $expectedSuffix;
        
        $this->assertEquals($expected, $result);
    }
    
    public function testBuildParamCacheKey_WithComplexParams(): void
    {
        $paramsArray = [
            'user' => [
                'id' => 123,
                'name' => 'test',
                'roles' => ['admin', 'editor']
            ],
            'filters' => [
                'status' => 'active',
                'date' => '2023-01-01'
            ],
            'pagination' => [
                'page' => 1,
                'limit' => 20
            ]
        ];
        
        $params = $this->createMock(JsonRpcParams::class);
        $params->expects($this->once())
            ->method('toArray')
            ->willReturn($paramsArray);
            
        // 使用反射访问protected方法
        $reflection = new \ReflectionClass(TestCacheableProcedure::class);
        $method = $reflection->getMethod('buildParamCacheKey');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->procedure, $params);
        
        $expectedPrefix = str_replace('\\', '-', TestCacheableProcedure::class);
        $expectedSuffix = md5(Json::encode($paramsArray));
        $expected = $expectedPrefix . '-' . $expectedSuffix;
        
        $this->assertEquals($expected, $result);
    }
    
    public function testGetCacheKey(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        // 默认返回测试缓存键
        $this->assertEquals('test-cache-key', $this->procedure->getCacheKey($request));
        
        // 测试修改后返回新的缓存键
        $this->procedure->setCacheKey('new-cache-key');
        $this->assertEquals('new-cache-key', $this->procedure->getCacheKey($request));
        
        // 测试空缓存键
        $this->procedure->setCacheKey('');
        $this->assertEquals('', $this->procedure->getCacheKey($request));
    }
    
    public function testGetCacheDuration(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        // 默认返回3600秒
        $this->assertEquals(3600, $this->procedure->getCacheDuration($request));
        
        // 测试修改后返回新的持续时间
        $this->procedure->setCacheDuration(1800);
        $this->assertEquals(1800, $this->procedure->getCacheDuration($request));
        
        // 测试0持续时间
        $this->procedure->setCacheDuration(0);
        $this->assertEquals(0, $this->procedure->getCacheDuration($request));
        
        // 测试负持续时间（虽然这种情况一般不会发生）
        $this->procedure->setCacheDuration(-1);
        $this->assertEquals(-1, $this->procedure->getCacheDuration($request));
    }
    
    public function testGetCacheTags(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        // 默认返回['tag1', 'tag2']
        $this->assertEquals(['tag1', 'tag2'], iterator_to_array($this->procedure->getCacheTags($request)));
        
        // 测试修改后返回新的标签
        $this->procedure->setCacheTags(['new-tag1', 'new-tag2', 'new-tag3']);
        $this->assertEquals(['new-tag1', 'new-tag2', 'new-tag3'], iterator_to_array($this->procedure->getCacheTags($request)));
        
        // 测试空标签数组
        $this->procedure->setCacheTags([]);
        $this->assertEquals([], iterator_to_array($this->procedure->getCacheTags($request)));
    }
    
    public function testExecute(): void
    {
        $this->assertEquals(['result' => 'success'], $this->procedure->execute());
    }
} 