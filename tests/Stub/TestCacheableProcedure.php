<?php

namespace Tourze\JsonRPCCacheBundle\Tests\Stub;

use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

class TestCacheableProcedure extends CacheableProcedure
{
    private string $cacheKey = 'test-cache-key';
    private int $cacheDuration = 3600;
    private array $cacheTags = ['tag1', 'tag2'];
    
    public function execute(): array
    {
        return ['result' => 'success'];
    }
    
    public function getCacheKey(JsonRpcRequest $request): string
    {
        return $this->cacheKey;
    }
    
    public function setCacheKey(?string $cacheKey): void
    {
        $this->cacheKey = $cacheKey ?? '';
    }
    
    public function getCacheDuration(JsonRpcRequest $request): int
    {
        return $this->cacheDuration;
    }
    
    public function setCacheDuration(int $cacheDuration): void
    {
        $this->cacheDuration = $cacheDuration;
    }
    
    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        return $this->cacheTags;
    }
    
    public function setCacheTags(array $cacheTags): void
    {
        $this->cacheTags = $cacheTags;
    }
}
