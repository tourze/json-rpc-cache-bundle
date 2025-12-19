<?php

namespace Tourze\JsonRPCCacheBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

/**
 * CacheableProcedure 测试类
 *
 * 测试抽象类 CacheableProcedure 的功能，使用 TestCacheableProcedureImpl 作为具体实现
 *
 * @internal
 */
#[CoversClass(CacheableProcedure::class)]
#[RunTestsInSeparateProcesses]
final class CacheableProcedureTest extends AbstractProcedureTestCase
{
    private ?TestCacheableProcedureImpl $procedure = null;

    private function createJsonRpcRequest(): JsonRpcRequest
    {
        $request = new JsonRpcRequest();
        $request->setMethod('test');

        return $request;
    }

    protected function onSetUp(): void
    {
        $this->procedure = null;
    }

    private function getProcedure(): TestCacheableProcedureImpl
    {
        if (null === $this->procedure) {
            $this->procedure = new TestCacheableProcedureImpl();
        }

        return $this->procedure;
    }

    public function testBuildParamCacheKeyWithEmptyParamsShouldReturnCorrectKey(): void
    {
        $params = new JsonRpcParams([]);

        $result = $this->getProcedure()->exposeBuildParamCacheKey($params);

        // 验证结果是一个有效的字符串且包含MD5哈希
        $this->assertStringContainsString('-', $result);
        $parts = explode('-', $result);
        $this->assertEquals(32, strlen(end($parts))); // MD5长度为32
    }

    public function testBuildParamCacheKeyWithSimpleParamsShouldReturnCorrectKey(): void
    {
        $paramsArray = ['id' => 123, 'name' => 'test'];
        $params = new JsonRpcParams($paramsArray);

        $result = $this->getProcedure()->exposeBuildParamCacheKey($params);

        // 验证结果是一个有效的字符串且包含MD5哈希
        $this->assertStringContainsString('-', $result);
        $parts = explode('-', $result);
        $this->assertEquals(32, strlen(end($parts))); // MD5长度为32
    }

    public function testBuildParamCacheKeyWithComplexParamsShouldReturnCorrectKey(): void
    {
        $paramsArray = [
            'user' => [
                'id' => 123,
                'name' => 'test',
                'roles' => ['admin', 'editor'],
            ],
            'filters' => [
                'status' => 'active',
                'date' => '2023-01-01',
            ],
            'pagination' => [
                'page' => 1,
                'limit' => 20,
            ],
        ];

        $params = new JsonRpcParams($paramsArray);

        $result = $this->getProcedure()->exposeBuildParamCacheKey($params);

        // 验证结果是一个有效的字符串且包含MD5哈希
        $this->assertStringContainsString('-', $result);
        $parts = explode('-', $result);
        $this->assertEquals(32, strlen(end($parts))); // MD5长度为32
    }

    public function testBuildParamCacheKeyWithSpecialCharactersShouldHandleCorrectly(): void
    {
        $paramsArray = [
            'text' => 'Special chars: !@#$%^&*()[]{}|;:,.<>?',
            'unicode' => '中文测试',
            'emoji' => '🚀😀',
            'quotes' => '"single\' and "double" quotes',
            'null_value' => null,
            'boolean' => true,
            'number' => 3.14159,
        ];

        $params = new JsonRpcParams($paramsArray);

        $result = $this->getProcedure()->exposeBuildParamCacheKey($params);

        // 验证结果是一个有效的字符串且包含MD5哈希
        $this->assertStringContainsString('-', $result);
        $parts = explode('-', $result);
        $this->assertEquals(32, strlen(end($parts))); // MD5长度为32
    }

    public function testBuildParamCacheKeyWithLargeDatasetShouldReturnConsistentHash(): void
    {
        $largeArray = [];
        for ($i = 0; $i < 1000; ++$i) {
            $largeArray["key_{$i}"] = "value_{$i}";
        }

        $params = new JsonRpcParams($largeArray);

        $result = $this->getProcedure()->exposeBuildParamCacheKey($params);

        // 验证大数据集也能正常生成缓存键
        $parts = explode('-', $result);
        $this->assertEquals(32, strlen(end($parts))); // MD5长度固定
    }

    public function testBuildParamCacheKeyWithSameParamsDifferentOrderShouldReturnDifferentKeys(): void
    {
        $params1 = ['a' => 1, 'b' => 2];
        $params2 = ['b' => 2, 'a' => 1];

        $mockParams1 = new JsonRpcParams($params1);
        $mockParams2 = new JsonRpcParams($params2);

        $key1 = $this->getProcedure()->exposeBuildParamCacheKey($mockParams1);
        $key2 = $this->getProcedure()->exposeBuildParamCacheKey($mockParams2);

        // 注意：由于JSON编码，不同顺序会产生不同的哈希
        // 这是预期行为，因为JSON编码保持键顺序
        $this->assertNotEquals($key1, $key2, '不同键顺序应该产生不同的缓存键');
        $this->assertIsString($key1);
        $this->assertIsString($key2);
    }

    public function testGetCacheKeyWithVariousInputsShouldReturnCorrectKeys(): void
    {
        $request = $this->createJsonRpcRequest();

        // 默认返回测试缓存键
        $this->assertEquals('test-cache-key', $this->getProcedure()->getCacheKey($request));

        // 测试修改后返回新的缓存键
        $this->getProcedure()->setCacheKey('new-cache-key');
        $this->assertEquals('new-cache-key', $this->getProcedure()->getCacheKey($request));

        // 测试空缓存键
        $this->getProcedure()->setCacheKey('');
        $this->assertEquals('', $this->getProcedure()->getCacheKey($request));

        // 测试特殊字符缓存键
        $this->getProcedure()->setCacheKey('cache:key-with_special.chars');
        $this->assertEquals('cache:key-with_special.chars', $this->getProcedure()->getCacheKey($request));
    }

    public function testGetCacheKeyWithNullValueShouldReturnEmptyString(): void
    {
        $request = $this->createJsonRpcRequest();

        $this->getProcedure()->setCacheKey(null);
        $this->assertEquals('', $this->getProcedure()->getCacheKey($request));
    }

    public function testGetCacheDurationWithVariousValuesShouldReturnCorrectDurations(): void
    {
        $request = $this->createJsonRpcRequest();

        // 默认返回3600秒
        $this->assertEquals(3600, $this->getProcedure()->getCacheDuration($request));

        // 测试修改后返回新的持续时间
        $this->getProcedure()->setCacheDuration(1800);
        $this->assertEquals(1800, $this->getProcedure()->getCacheDuration($request));

        // 测试0持续时间
        $this->getProcedure()->setCacheDuration(0);
        $this->assertEquals(0, $this->getProcedure()->getCacheDuration($request));

        // 测试负持续时间（虽然这种情况一般不会发生）
        $this->getProcedure()->setCacheDuration(-1);
        $this->assertEquals(-1, $this->getProcedure()->getCacheDuration($request));

        // 测试极大值
        $this->getProcedure()->setCacheDuration(PHP_INT_MAX);
        $this->assertEquals(PHP_INT_MAX, $this->getProcedure()->getCacheDuration($request));
    }

    public function testGetCacheDurationWithBoundaryValuesShouldHandleCorrectly(): void
    {
        $request = $this->createJsonRpcRequest();

        // 测试极小值
        $this->getProcedure()->setCacheDuration(PHP_INT_MIN);
        $this->assertEquals(PHP_INT_MIN, $this->getProcedure()->getCacheDuration($request));

        // 测试1秒
        $this->getProcedure()->setCacheDuration(1);
        $this->assertEquals(1, $this->getProcedure()->getCacheDuration($request));

        // 测试常见的缓存时间（1小时、1天、1周）
        $this->getProcedure()->setCacheDuration(3600); // 1小时
        $this->assertEquals(3600, $this->getProcedure()->getCacheDuration($request));

        $this->getProcedure()->setCacheDuration(86400); // 1天
        $this->assertEquals(86400, $this->getProcedure()->getCacheDuration($request));

        $this->getProcedure()->setCacheDuration(604800); // 1周
        $this->assertEquals(604800, $this->getProcedure()->getCacheDuration($request));
    }

    public function testGetCacheTagsWithVariousTagArraysShouldReturnCorrectTags(): void
    {
        $request = $this->createJsonRpcRequest();

        // 默认返回['tag1', 'tag2']
        $this->assertEquals(['tag1', 'tag2'], iterator_to_array($this->getProcedure()->getCacheTags($request)));

        // 测试修改后返回新的标签
        $this->getProcedure()->setCacheTags(['new-tag1', 'new-tag2', 'new-tag3']);
        $this->assertEquals(['new-tag1', 'new-tag2', 'new-tag3'], iterator_to_array($this->getProcedure()->getCacheTags($request)));

        // 测试空标签数组
        $this->getProcedure()->setCacheTags([]);
        $this->assertEquals([], iterator_to_array($this->getProcedure()->getCacheTags($request)));

        // 测试单个标签
        $this->getProcedure()->setCacheTags(['single-tag']);
        $this->assertEquals(['single-tag'], iterator_to_array($this->getProcedure()->getCacheTags($request)));
    }

    public function testGetCacheTagsWithSpecialTagValuesShouldHandleCorrectly(): void
    {
        $request = $this->createJsonRpcRequest();

        // 测试包含特殊字符的标签
        $this->getProcedure()->setCacheTags(['tag:with:colons', 'tag-with-dashes', 'tag_with_underscores']);
        $expected = ['tag:with:colons', 'tag-with-dashes', 'tag_with_underscores'];
        $this->assertEquals($expected, iterator_to_array($this->getProcedure()->getCacheTags($request)));

        // 测试数字标签
        $this->getProcedure()->setCacheTags(['123', '456']);
        $this->assertEquals(['123', '456'], iterator_to_array($this->getProcedure()->getCacheTags($request)));

        // 测试包含null和空字符串的标签数组 - null值会被过滤掉
        $this->getProcedure()->setCacheTags(['valid-tag', null, '', 'another-valid-tag']);
        $expected = ['valid-tag', '', 'another-valid-tag'];
        $this->assertEquals($expected, iterator_to_array($this->getProcedure()->getCacheTags($request)));
    }

    public function testGetCacheTagsWithLargeTagArrayShouldReturnAllTags(): void
    {
        $request = $this->createJsonRpcRequest();

        $largeTags = [];
        for ($i = 0; $i < 100; ++$i) {
            $largeTags[] = "tag-{$i}";
        }

        $this->getProcedure()->setCacheTags($largeTags);
        $result = iterator_to_array($this->getProcedure()->getCacheTags($request));

        $this->assertEquals($largeTags, $result);
        $this->assertCount(100, $result);
    }

    public function testExecuteShouldReturnExpectedResult(): void
    {
        $param = new TestParam();
        $result = $this->getProcedure()->execute($param);
        $this->assertEquals(['result' => 'success'], $result->toArray());
    }

    public function testServiceSubscriberInterfaceShouldBeImplemented(): void
    {
        // 验证 getSubscribedServices 方法存在并返回数组
        $subscribedServices = CacheableProcedure::getSubscribedServices();
        $this->assertIsArray($subscribedServices);
    }

    public function testBaseProcedureShouldBeExtended(): void
    {
        // 验证 execute 方法可以被调用并返回预期结果
        $param = new TestParam();
        $result = $this->getProcedure()->execute($param);
        $resultArray = $result->toArray();
        $this->assertIsArray($resultArray);
        $this->assertArrayHasKey('result', $resultArray);
        $this->assertEquals('success', $resultArray['result']);
    }

    public function testCacheKeyConsistencySameProcedureInstanceShouldReturnSameKey(): void
    {
        $request = $this->createJsonRpcRequest();

        $this->getProcedure()->setCacheKey('consistent-key');

        $key1 = $this->getProcedure()->getCacheKey($request);
        $key2 = $this->getProcedure()->getCacheKey($request);
        $key3 = $this->getProcedure()->getCacheKey($request);

        $this->assertEquals($key1, $key2);
        $this->assertEquals($key2, $key3);
        $this->assertEquals('consistent-key', $key1);
    }

    public function testCacheDurationConsistencySameProcedureInstanceShouldReturnSameDuration(): void
    {
        $request = $this->createJsonRpcRequest();

        $this->getProcedure()->setCacheDuration(7200);

        $duration1 = $this->getProcedure()->getCacheDuration($request);
        $duration2 = $this->getProcedure()->getCacheDuration($request);
        $duration3 = $this->getProcedure()->getCacheDuration($request);

        $this->assertEquals($duration1, $duration2);
        $this->assertEquals($duration2, $duration3);
        $this->assertEquals(7200, $duration1);
    }

    public function testCacheTagsConsistencySameProcedureInstanceShouldReturnSameTags(): void
    {
        $request = $this->createJsonRpcRequest();

        $tags = ['consistency-tag1', 'consistency-tag2'];
        $this->getProcedure()->setCacheTags($tags);

        $tags1 = iterator_to_array($this->getProcedure()->getCacheTags($request));
        $tags2 = iterator_to_array($this->getProcedure()->getCacheTags($request));
        $tags3 = iterator_to_array($this->getProcedure()->getCacheTags($request));

        $this->assertEquals($tags1, $tags2);
        $this->assertEquals($tags2, $tags3);
        $this->assertEquals($tags, $tags1);
    }
}
