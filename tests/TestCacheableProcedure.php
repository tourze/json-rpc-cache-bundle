<?php

namespace Tourze\JsonRPCCacheBundle\Tests;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

/**
 * 测试用的可缓存过程类
 *
 * @internal
 */
#[Autoconfigure(public: true)]
#[MethodTag(name: 'test')]
#[MethodDoc(summary: 'Test Cache Procedure', description: 'A test procedure for cache functionality')]
#[MethodExpose(method: 'testCacheable')]
final class TestCacheableProcedure extends CacheableProcedure
{
    private ?string $cacheKey = 'test-cache-key';

    private int $cacheDuration = 3600;

    /** @var array<string> */
    private array $cacheTags = ['tag1', 'tag2'];

    public function execute(): array
    {
        return ['result' => 'success'];
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

    /** @param array<string|null> $cacheTags */
    public function setCacheTags(array $cacheTags): void
    {
        $this->cacheTags = array_values(array_filter($cacheTags, static fn ($tag): bool => null !== $tag));
    }
}
