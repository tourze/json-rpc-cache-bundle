<?php

namespace Tourze\JsonRPCCacheBundle\Procedure;

use Symfony\Contracts\Cache\CacheInterface;
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
    abstract public function getCacheKey(JsonRpcRequest $request): string;

    /**
     * 过期时间，单位秒
     */
    abstract public function getCacheDuration(JsonRpcRequest $request): int;

    /**
     * 缓存标签
     * 如果希望不使用标签，可以yield null；
     * 一般这里使用实体来关联，用 yield CacheHelper::getClassTags(Code::class); 这种写法
     */
    abstract public function getCacheTags(JsonRpcRequest $request): iterable;

    /**
     * 重写父类的__invoke方法，添加缓存逻辑
     */
    public function __invoke(JsonRpcRequest $request): mixed
    {
        // 权限检查
        $this->getGrantService()->checkProcedure($this);

        // 获取缓存键
        $cacheKey = $this->getCacheKey($request);
        $cacheDuration = $this->getCacheDuration($request);

        // 如果缓存键为空或缓存时间小于等于0，则跳过缓存直接执行父方法
        if ('' === $cacheKey || $cacheDuration <= 0) {
            return parent::__invoke($request);
        }

        // 使用缓存
        return $this->getCache()->get($cacheKey, function ($item) use ($request, $cacheDuration) {
            // 设置缓存过期时间
            $item->expiresAfter($cacheDuration);

            // 设置缓存标签
            $tags = $this->getCacheTags($request);
            if (!empty($tags)) {
                $item->tag($tags);
            }

            // 执行父类方法获取结果
            return parent::__invoke($request);
        });
    }
}
