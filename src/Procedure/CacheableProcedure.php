<?php

namespace Tourze\JsonRPCCacheBundle\Procedure;

use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Yiisoft\Json\Json;

/**
 * 有一些接口，我们需要做缓存控制，默认是根据接口名和参数来做缓存key
 */
abstract class CacheableProcedure extends BaseProcedure implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

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
}
