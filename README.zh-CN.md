# JsonRPC Cache Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/packagist/php-v/tourze/json-rpc-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-cache-bundle)
[![Latest Version](https://img.shields.io/packagist/v/tourze/json-rpc-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-cache-bundle)
[![License](https://img.shields.io/packagist/l/tourze/json-rpc-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-cache-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?branch=master&style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Coverage Status](https://img.shields.io/codecov/c/github/tourze/php-monorepo?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/json-rpc-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-cache-bundle)

一个为 JSON-RPC 过程提供缓存功能的 Symfony Bundle，提供自动缓存管理和灵活的配置选项。

## 目录

- [特性](#特性)
- [安装](#安装)
- [快速开始](#快速开始)
  - [1. 创建可缓存过程](#1-创建可缓存过程)
  - [2. Bundle 配置](#2-bundle-配置)
  - [3. 使用示例](#3-使用示例)
- [配置](#配置)
  - [缓存键生成](#缓存键生成)
  - [缓存标签](#缓存标签)
  - [TTL 配置](#ttl-配置)
- [高级用法](#高级用法)
  - [条件缓存](#条件缓存)
  - [缓存失效](#缓存失效)
- [架构](#架构)
- [系统要求](#系统要求)
- [贡献指南](#贡献指南)
- [许可证](#许可证)

## 特性

- **自动缓存**: 基于请求参数无缝缓存 JSON-RPC 过程结果
- **灵活的缓存键**: 使用请求参数和过程类名构建自定义缓存键
- **缓存标签**: 支持使用标签进行缓存失效
- **可配置 TTL**: 为不同过程设置自定义缓存时长
- **事件驱动**: 使用 Symfony 事件系统进行透明缓存管理

## 安装

```bash
composer require tourze/json-rpc-cache-bundle
```

## 快速开始

### 1. 创建可缓存过程

```php
<?php

namespace App\Procedure;

use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

class GetUserInfoProcedure extends CacheableProcedure
{
    public function getCacheKey(JsonRpcRequest $request): string
    {
        return $this->buildParamCacheKey($request->getParams());
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        return 3600; // 1 小时
    }

    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        yield 'user_info';
        yield 'user_' . $request->getParams()->get('user_id');
    }

    public function execute(JsonRpcRequest $request): mixed
    {
        // 您的过程逻辑
        return $this->userService->getUserInfo($request->getParams()->get('user_id'));
    }
}
```

### 2. Bundle 配置

Bundle 会自动配置。只需确保在 `config/packages/cache.yaml` 中配置了缓存适配器：

```yaml
framework:
    cache:
        default_redis_provider: 'redis://localhost:6379'
        pools:
            cache.app:
                adapter: cache.adapter.redis
```

### 3. 使用示例

当您的过程继承 `CacheableProcedure` 后，缓存会自动工作：

```php
// 第一次调用 - 执行过程并缓存结果
$result = $jsonRpcDispatcher->dispatch('GetUserInfo', ['user_id' => 123]);

// 第二次调用 - 返回缓存结果
$result = $jsonRpcDispatcher->dispatch('GetUserInfo', ['user_id' => 123]);
```

## 配置

### 缓存键生成

Bundle 提供了 `buildParamCacheKey()` 辅助方法，基于以下内容创建缓存键：
- 过程类名（命名空间分隔符替换为短横线）
- 参数 JSON 编码的 MD5 哈希

```php
protected function buildParamCacheKey(JsonRpcParams $params): string
{
    $parts = [
        str_replace('\\', '-', static::class),
        md5(Json::encode($params->toArray())),
    ];

    return implode('-', $parts);
}
```

### 缓存标签

实现 `getCacheTags()` 返回用于失效的缓存标签：

```php
public function getCacheTags(JsonRpcRequest $request): iterable
{
    yield 'user_data';
    yield 'user_' . $request->getParams()->get('user_id');
}
```

### TTL 配置

通过 `getCacheDuration()` 设置缓存时长（秒）：

```php
public function getCacheDuration(JsonRpcRequest $request): int
{
    // 根据请求参数设置不同的 TTL
    if ($request->getParams()->get('is_premium')) {
        return 7200; // 高级用户 2 小时
    }
    
    return 3600; // 普通用户 1 小时
}
```

## 高级用法

### 条件缓存

要为特定请求禁用缓存，从 `getCacheKey()` 返回空字符串：

```php
public function getCacheKey(JsonRpcRequest $request): string
{
    // 管理员用户不缓存
    if ($request->getParams()->get('user_role') === 'admin') {
        return '';
    }
    
    return $this->buildParamCacheKey($request->getParams());
}
```

### 缓存失效

使用 Symfony 缓存适配器通过标签使缓存失效：

```php
use Symfony\Component\Cache\Adapter\AdapterInterface;

public function invalidateUserCache(int $userId): void
{
    $this->cache->invalidateTags(['user_' . $userId]);
}
```

## 架构

Bundle 使用两个事件监听器：

1. **BeforeMethodApplyEvent** (优先级: 0)：检查缓存结果，如果可用则返回
2. **AfterMethodApplyEvent** (优先级: -99)：将过程结果存储到缓存中，设置 TTL 和标签

这种方式确保缓存是透明的，不会干扰其他 bundle 功能（如安全检查）。

## 系统要求

- PHP 8.1+
- Symfony 6.4+
- tourze/json-rpc-core
- 已配置的缓存适配器（Redis、Memcached 等）

## 贡献指南

欢迎贡献！请遵循以下准则：

1. Fork 仓库
2. 创建功能分支
3. 编写代码并添加测试
4. 运行 `phpstan` 和 `phpunit` 确保代码质量
5. 提交 Pull Request

如需报告 Bug 或提出新功能需求，请使用 [GitHub Issues](https://github.com/tourze/php-monorepo/issues)。

## 许可证

此 bundle 在 MIT 许可证下发布。详情请参阅 LICENSE 文件。
