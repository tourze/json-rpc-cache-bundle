# JsonRPC Cache Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/packagist/php-v/tourze/json-rpc-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-cache-bundle)
[![Latest Version](https://img.shields.io/packagist/v/tourze/json-rpc-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-cache-bundle)
[![License](https://img.shields.io/packagist/l/tourze/json-rpc-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-cache-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?branch=master&style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Coverage Status](https://img.shields.io/codecov/c/github/tourze/php-monorepo?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/json-rpc-cache-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/json-rpc-cache-bundle)

A Symfony bundle that provides caching capabilities for JSON-RPC procedures, offering automatic cache management with flexible configuration options.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
  - [1. Create a Cacheable Procedure](#1-create-a-cacheable-procedure)
  - [2. Bundle Configuration](#2-bundle-configuration)
  - [3. Usage in Action](#3-usage-in-action)
- [Configuration](#configuration)
  - [Cache Key Generation](#cache-key-generation)
  - [Cache Tags](#cache-tags)
  - [TTL Configuration](#ttl-configuration)
- [Advanced Usage](#advanced-usage)
  - [Conditional Caching](#conditional-caching)
  - [Cache Invalidation](#cache-invalidation)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Automatic Caching**: Seamlessly cache JSON-RPC procedure results based on request parameters
- **Flexible Cache Keys**: Build custom cache keys using request parameters and procedure class names
- **Cache Tags**: Support for cache invalidation using tags
- **Configurable TTL**: Set custom cache durations for different procedures
- **Event-Driven**: Uses Symfony's event system for transparent cache management

## Installation

```bash
composer require tourze/json-rpc-cache-bundle
```

## Quick Start

### 1. Create a Cacheable Procedure

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
        return 3600; // 1 hour
    }

    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        yield 'user_info';
        yield 'user_' . $request->getParams()->get('user_id');
    }

    public function execute(JsonRpcRequest $request): mixed
    {
        // Your procedure logic here
        return $this->userService->getUserInfo($request->getParams()->get('user_id'));
    }
}
```

### 2. Bundle Configuration

The bundle auto-configures itself. Just ensure your cache adapter is configured in `config/packages/cache.yaml`:

```yaml
framework:
    cache:
        default_redis_provider: 'redis://localhost:6379'
        pools:
            cache.app:
                adapter: cache.adapter.redis
```

### 3. Usage in Action

Once your procedure extends `CacheableProcedure`, the caching happens automatically:

```php
// First call - executes procedure and caches result
$result = $jsonRpcDispatcher->dispatch('GetUserInfo', ['user_id' => 123]);

// Second call - returns cached result
$result = $jsonRpcDispatcher->dispatch('GetUserInfo', ['user_id' => 123]);
```

## Configuration

### Cache Key Generation

The bundle provides a helper method `buildParamCacheKey()` that creates cache keys based on:
- The procedure class name (with namespace separators replaced by dashes)
- MD5 hash of the JSON-encoded parameters

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

### Cache Tags

Implement `getCacheTags()` to return cache tags for invalidation:

```php
public function getCacheTags(JsonRpcRequest $request): iterable
{
    yield 'user_data';
    yield 'user_' . $request->getParams()->get('user_id');
}
```

### TTL Configuration

Set cache duration in seconds via `getCacheDuration()`:

```php
public function getCacheDuration(JsonRpcRequest $request): int
{
    // Different TTL based on request parameters
    if ($request->getParams()->get('is_premium')) {
        return 7200; // 2 hours for premium users
    }
    
    return 3600; // 1 hour for regular users
}
```

## Advanced Usage

### Conditional Caching

To disable caching for specific requests, return an empty string from `getCacheKey()`:

```php
public function getCacheKey(JsonRpcRequest $request): string
{
    // Don't cache for admin users
    if ($request->getParams()->get('user_role') === 'admin') {
        return '';
    }
    
    return $this->buildParamCacheKey($request->getParams());
}
```

### Cache Invalidation

Use Symfony's cache adapter to invalidate cache by tags:

```php
use Symfony\Component\Cache\Adapter\AdapterInterface;

public function invalidateUserCache(int $userId): void
{
    $this->cache->invalidateTags(['user_' . $userId]);
}
```

## Architecture

The bundle uses two event listeners:

1. **BeforeMethodApplyEvent** (priority: 0): Checks for cached results and returns them if available
2. **AfterMethodApplyEvent** (priority: -99): Stores procedure results in cache with configured TTL and tags

This approach ensures that caching is transparent and doesn't interfere with other bundle functionality like security checks.

## Requirements

- PHP 8.1+
- Symfony 6.4+
- tourze/json-rpc-core
- A configured cache adapter (Redis, Memcached, etc.)

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Make your changes with tests
4. Run `phpstan` and `phpunit` to ensure code quality
5. Submit a pull request

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/tourze/php-monorepo/issues).

## License

This bundle is released under the MIT License. See the bundled LICENSE file for details.