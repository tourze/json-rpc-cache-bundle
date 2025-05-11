<?php

namespace Tourze\JsonRPCCacheBundle\EventSubscriber;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\JsonRPC\Core\Event\AfterMethodApplyEvent;
use Tourze\JsonRPC\Core\Event\BeforeMethodApplyEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

class CacheSubscriber
{
    public function __construct(
        private readonly AdapterInterface $cache,
    )
    {
    }

    /**
     * 这里的优先级一定不能高，因为其他模块实现了权限检查
     */
    #[AsEventListener(priority: 0)]
    public function beforeMethodApply(BeforeMethodApplyEvent $event): void
    {
        $procedure = $event->getMethod();
        if (!$procedure instanceof CacheableProcedure) {
            return;
        }

        // 如果能查到缓存，这里直接返回
        $item = $this->cache->getItem($procedure->getCacheKey($event->getRequest()));
        if (!$item->isHit()) {
            return;
        }
        $event->setResult($item->get());
    }

    /**
     * 最后才处理
     */
    #[AsEventListener(priority: -99)]
    public function afterMethodApply(AfterMethodApplyEvent $event): void
    {
        $procedure = $event->getMethod();
        if (!$procedure instanceof CacheableProcedure) {
            return;
        }

        $item = $this->cache->getItem($procedure->getCacheKey($event->getRequest()));
        $item->set($event->getResult());
        $item->expiresAfter($procedure->getCacheDuration($event->getRequest()));

        $this->assignTags($item, $procedure, $event->getRequest());

        $this->cache->save($item);
    }

    /**
     * 缓存标签声明
     */
    private function assignTags(CacheItem $item, CacheableProcedure $procedure, JsonRpcRequest $request): void
    {
        $tags = [];
        foreach ($procedure->getCacheTags($request) as $tag) {
            if (!$tag) {
                continue;
            }
            $tags[] = $tag;
        }
        if (empty($tags)) {
            return;
        }
        $item->tag($tags);
    }
}
