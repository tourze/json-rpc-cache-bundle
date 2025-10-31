<?php

namespace Tourze\JsonRPCCacheBundle\EventSubscriber;

use Monolog\Attribute\WithMonologChannel;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\JsonRPC\Core\Event\AfterMethodApplyEvent;
use Tourze\JsonRPC\Core\Event\BeforeMethodApplyEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

#[WithMonologChannel(channel: 'json_rpc_cache')]
class CacheSubscriber
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
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

        if ($item instanceof CacheItem) {
            $this->assignTags($item, $procedure, $event->getRequest());
        }

        $this->cache->save($item);
    }

    /**
     * 缓存标签声明
     */
    private function assignTags(CacheItem $item, CacheableProcedure $procedure, JsonRpcRequest $request): void
    {
        $tags = [];
        foreach ($procedure->getCacheTags($request) as $tag) {
            if ('' === $tag) {
                continue;
            }
            $tags[] = $tag;
        }
        if ([] === $tags) {
            return;
        }

        try {
            $item->tag($tags);
        } catch (LogicException $e) {
            // 缓存池不支持标签功能时，忽略标签设置
            // 缓存仍然可以正常工作，只是无法按标签清理
            $this->logger->error('获取缓存标签失败，缓存不支持标签功能', [
                'exception' => $e,
            ]);
        }
    }
}
