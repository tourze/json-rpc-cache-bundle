<?php

namespace Tourze\JsonRPCCacheBundle\Procedure;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCSecurityBundle\Service\GrantService;
use Yiisoft\Json\Json;

/**
 * 有一些接口，我们需要做缓存控制，默认是根据接口名和参数来做缓存key
 */
abstract class CacheableProcedure extends BaseProcedure implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

    #[SubscribedService]
    private function getCache(): CacheInterface
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService]
    private function getGrantService(): GrantService
    {
        return $this->container->get(__METHOD__);
    }

    protected function buildParamCacheKey(JsonRpcParams $params): string
    {
        $parts = [
            str_replace('\\', '-', static::class),
            md5(Json::encode($params->toArray())),
        ];
        return implode('-', $parts);
    }

    /**
     * 生成缓存key
     * 如果返回空字符串，则代表不需要缓存
     */
    abstract protected function getCacheKey(JsonRpcRequest $request): string;

    /**
     * 过期时间，单位秒
     */
    abstract protected function getCacheDuration(JsonRpcRequest $request): int;

    /**
     * 缓存标签
     * 如果希望不使用标签，可以yield null；
     * 一般这里使用实体来关联，用 yield CacheHelper::getClassTags(Code::class); 这种写法
     */
    abstract protected function getCacheTags(JsonRpcRequest $request): iterable;

    public function __invoke(JsonRpcRequest $request): mixed
    {
        $this->getGrantService()->checkProcedure($this);

        $key = $this->getCacheKey($request);
        if (empty($key)) {
            return parent::__invoke($request);
        }

        $duration = $this->getCacheDuration($request);
        if ($duration <= 0) {
            return parent::__invoke($request);
        }

        return $this->getCache()->get($key, function (ItemInterface $item) use ($request, $duration) {
            $item->expiresAfter($duration); // 指定时间后过期

            // 缓存标签声明
            $tags = [];
            foreach ($this->getCacheTags($request) as $tag) {
                if (!$tag) {
                    continue;
                }
                $tags[] = $tag;
            }
            if (!empty($tags)) {
                $item->tag($tags);
            }

            return parent::__invoke($request);
        });
    }
}
