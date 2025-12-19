<?php

namespace Tourze\JsonRPCCacheBundle\Tests\Procedure;

use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

/**
 * 用于 CacheableProcedureTest 的测试实现类
 *
 * 此类提供了一个具体实现，用于测试抽象类 CacheableProcedure 的功能
 *
 * @internal
 */
#[MethodTag(name: 'test')]
#[MethodDoc(summary: 'Test Cacheable Procedure Implementation')]
#[MethodExpose(method: 'testCacheableProcedureImpl')]
final class TestCacheableProcedureImpl extends CacheableProcedure
{
    private ?string $cacheKey = 'test-cache-key';

    private int $cacheDuration = 3600;

    /** @var array<string> */
    private array $cacheTags = ['tag1', 'tag2'];

    public function execute(RpcParamInterface $param): ArrayResult
    {
        return new ArrayResult(['result' => 'success']);
    }

    public function getCacheKey(JsonRpcRequest $request): string
    {
        return $this->cacheKey ?? '';
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        return $this->cacheDuration;
    }

    /**
     * @return iterable<string>
     */
    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        yield from $this->cacheTags;
    }

    public function setCacheKey(?string $cacheKey): void
    {
        $this->cacheKey = $cacheKey;
    }

    public function setCacheDuration(int $cacheDuration): void
    {
        $this->cacheDuration = $cacheDuration;
    }

    /**
     * @param array<string|null> $cacheTags
     */
    public function setCacheTags(array $cacheTags): void
    {
        $this->cacheTags = array_values(array_filter($cacheTags, static fn ($tag): bool => null !== $tag));
    }

    /**
     * 暴露 protected 方法用于测试
     */
    public function exposeBuildParamCacheKey(JsonRpcParams $params): string
    {
        return $this->buildParamCacheKey($params);
    }
}
