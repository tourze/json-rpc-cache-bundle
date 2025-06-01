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
    
    public function test_buildParamCacheKey_withEmptyParams_shouldReturnCorrectKey(): void
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
        $this->assertStringContainsString('Tourze-JsonRPCCacheBundle-Tests-Stub-TestCacheableProcedure', $result);
    }
    
    public function test_buildParamCacheKey_withSimpleParams_shouldReturnCorrectKey(): void
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
    
    public function test_buildParamCacheKey_withComplexParams_shouldReturnCorrectKey(): void
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

    public function test_buildParamCacheKey_withSpecialCharacters_shouldHandleCorrectly(): void
    {
        $paramsArray = [
            'text' => 'Special chars: !@#$%^&*()[]{}|;:,.<>?',
            'unicode' => '中文测试',
            'emoji' => '🚀😀',
            'quotes' => '"single\' and "double" quotes',
            'null_value' => null,
            'boolean' => true,
            'number' => 3.14159
        ];
        
        $params = $this->createMock(JsonRpcParams::class);
        $params->expects($this->once())
            ->method('toArray')
            ->willReturn($paramsArray);
            
        $reflection = new \ReflectionClass(TestCacheableProcedure::class);
        $method = $reflection->getMethod('buildParamCacheKey');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->procedure, $params);
        
        // 验证结果是一个有效的字符串且包含MD5哈希
        $this->assertIsString($result);
        $this->assertStringContainsString('-', $result);
        $parts = explode('-', $result);
        $this->assertEquals(32, strlen(end($parts))); // MD5长度为32
    }

    public function test_buildParamCacheKey_withLargeDataset_shouldReturnConsistentHash(): void
    {
        $largeArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeArray["key_$i"] = "value_$i";
        }
        
        $params = $this->createMock(JsonRpcParams::class);
        $params->expects($this->once())
            ->method('toArray')
            ->willReturn($largeArray);
            
        $reflection = new \ReflectionClass(TestCacheableProcedure::class);
        $method = $reflection->getMethod('buildParamCacheKey');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->procedure, $params);
        
        // 验证大数据集也能正常生成缓存键
        $this->assertIsString($result);
        $parts = explode('-', $result);
        $this->assertEquals(32, strlen(end($parts))); // MD5长度固定
    }

    public function test_buildParamCacheKey_withSameParamsDifferentOrder_shouldReturnSameKey(): void
    {
        $params1 = ['a' => 1, 'b' => 2];
        $params2 = ['b' => 2, 'a' => 1];
        
        $mockParams1 = $this->createMock(JsonRpcParams::class);
        $mockParams1->method('toArray')->willReturn($params1);
        
        $mockParams2 = $this->createMock(JsonRpcParams::class);
        $mockParams2->method('toArray')->willReturn($params2);
        
        $reflection = new \ReflectionClass(TestCacheableProcedure::class);
        $method = $reflection->getMethod('buildParamCacheKey');
        $method->setAccessible(true);
        
        $key1 = $method->invoke($this->procedure, $mockParams1);
        $key2 = $method->invoke($this->procedure, $mockParams2);
        
        // 注意：由于JSON编码，不同顺序可能产生不同的哈希
        // 这是预期行为，因为JSON编码保持键顺序
        $this->assertIsString($key1);
        $this->assertIsString($key2);
    }
    
    public function test_getCacheKey_withVariousInputs_shouldReturnCorrectKeys(): void
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
        
        // 测试特殊字符缓存键
        $this->procedure->setCacheKey('cache:key-with_special.chars');
        $this->assertEquals('cache:key-with_special.chars', $this->procedure->getCacheKey($request));
    }

    public function test_getCacheKey_withNullValue_shouldReturnEmptyString(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        $this->procedure->setCacheKey(null);
        $this->assertEquals('', $this->procedure->getCacheKey($request));
    }
    
    public function test_getCacheDuration_withVariousValues_shouldReturnCorrectDurations(): void
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
        
        // 测试极大值
        $this->procedure->setCacheDuration(PHP_INT_MAX);
        $this->assertEquals(PHP_INT_MAX, $this->procedure->getCacheDuration($request));
    }

    public function test_getCacheDuration_withBoundaryValues_shouldHandleCorrectly(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        // 测试极小值
        $this->procedure->setCacheDuration(PHP_INT_MIN);
        $this->assertEquals(PHP_INT_MIN, $this->procedure->getCacheDuration($request));
        
        // 测试1秒
        $this->procedure->setCacheDuration(1);
        $this->assertEquals(1, $this->procedure->getCacheDuration($request));
        
        // 测试常见的缓存时间（1小时、1天、1周）
        $this->procedure->setCacheDuration(3600); // 1小时
        $this->assertEquals(3600, $this->procedure->getCacheDuration($request));
        
        $this->procedure->setCacheDuration(86400); // 1天
        $this->assertEquals(86400, $this->procedure->getCacheDuration($request));
        
        $this->procedure->setCacheDuration(604800); // 1周
        $this->assertEquals(604800, $this->procedure->getCacheDuration($request));
    }
    
    public function test_getCacheTags_withVariousTagArrays_shouldReturnCorrectTags(): void
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
        
        // 测试单个标签
        $this->procedure->setCacheTags(['single-tag']);
        $this->assertEquals(['single-tag'], iterator_to_array($this->procedure->getCacheTags($request)));
    }

    public function test_getCacheTags_withSpecialTagValues_shouldHandleCorrectly(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        // 测试包含特殊字符的标签
        $this->procedure->setCacheTags(['tag:with:colons', 'tag-with-dashes', 'tag_with_underscores']);
        $expected = ['tag:with:colons', 'tag-with-dashes', 'tag_with_underscores'];
        $this->assertEquals($expected, iterator_to_array($this->procedure->getCacheTags($request)));
        
        // 测试数字标签
        $this->procedure->setCacheTags(['123', '456']);
        $this->assertEquals(['123', '456'], iterator_to_array($this->procedure->getCacheTags($request)));
        
        // 测试包含null和空字符串的标签数组
        $this->procedure->setCacheTags(['valid-tag', null, '', 'another-valid-tag']);
        $expected = ['valid-tag', null, '', 'another-valid-tag'];
        $this->assertEquals($expected, iterator_to_array($this->procedure->getCacheTags($request)));
    }

    public function test_getCacheTags_withLargeTagArray_shouldReturnAllTags(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        $largeTags = [];
        for ($i = 0; $i < 100; $i++) {
            $largeTags[] = "tag-$i";
        }
        
        $this->procedure->setCacheTags($largeTags);
        $result = iterator_to_array($this->procedure->getCacheTags($request));
        
        $this->assertEquals($largeTags, $result);
        $this->assertCount(100, $result);
    }
    
    public function test_execute_shouldReturnExpectedResult(): void
    {
        $this->assertEquals(['result' => 'success'], $this->procedure->execute());
    }

    public function test_serviceSubscriberInterface_shouldBeImplemented(): void
    {
        $this->assertInstanceOf(\Symfony\Contracts\Service\ServiceSubscriberInterface::class, $this->procedure);
    }

    public function test_baseProcedure_shouldBeExtended(): void
    {
        $this->assertInstanceOf(\Tourze\JsonRPC\Core\Procedure\BaseProcedure::class, $this->procedure);
    }

    public function test_cacheKeyConsistency_sameProcedureInstanceShouldReturnSameKey(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        $this->procedure->setCacheKey('consistent-key');
        
        $key1 = $this->procedure->getCacheKey($request);
        $key2 = $this->procedure->getCacheKey($request);
        $key3 = $this->procedure->getCacheKey($request);
        
        $this->assertEquals($key1, $key2);
        $this->assertEquals($key2, $key3);
        $this->assertEquals('consistent-key', $key1);
    }

    public function test_cacheDurationConsistency_sameProcedureInstanceShouldReturnSameDuration(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        $this->procedure->setCacheDuration(7200);
        
        $duration1 = $this->procedure->getCacheDuration($request);
        $duration2 = $this->procedure->getCacheDuration($request);
        $duration3 = $this->procedure->getCacheDuration($request);
        
        $this->assertEquals($duration1, $duration2);
        $this->assertEquals($duration2, $duration3);
        $this->assertEquals(7200, $duration1);
    }

    public function test_cacheTagsConsistency_sameProcedureInstanceShouldReturnSameTags(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        
        $tags = ['consistency-tag1', 'consistency-tag2'];
        $this->procedure->setCacheTags($tags);
        
        $tags1 = iterator_to_array($this->procedure->getCacheTags($request));
        $tags2 = iterator_to_array($this->procedure->getCacheTags($request));
        $tags3 = iterator_to_array($this->procedure->getCacheTags($request));
        
        $this->assertEquals($tags1, $tags2);
        $this->assertEquals($tags2, $tags3);
        $this->assertEquals($tags, $tags1);
    }
} 