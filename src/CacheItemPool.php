<?php

namespace DivineOmega\DOFileCachePSR6;

use Psr\Cache\CacheItemPoolInterface;
use DivineOmega\DOFileCache\DOFileCache;
use Psr\Cache\CacheItemInterface;
use Traversable;  // Built-in PHP Traversable interface


class CacheItemPool implements CacheItemPoolInterface
{
    private $doFileCache;
    private $deferredItems = [];

    public function __construct()
    {
        $this->doFileCache = new DOFileCache();
    }

    public function changeConfig(array $config)
    {
        return $this->doFileCache->changeConfig($config);
    }

    private function sanityCheckKey($key) 
    {
        if (!is_string($key)) {
            throw new CacheInvalidArgumentException;
        }

        $invalidChars = ['{', '}', '(', ')', '/', '\\', '@', ':'];

        foreach($invalidChars as $invalidChar) {
            if (stripos($key, $invalidChar)!==false) {
                throw new CacheInvalidArgumentException;
            }
        }

    }

    public function getItem($key): CacheItemInterface
    {
        $this->sanityCheckKey($key);

        if (array_key_exists($key, $this->deferredItems)) {
            return $this->deferredItems[$key];
        }

        return new CacheItem($key, $this->doFileCache->get($key));
    }

    public function getItems(array $keys = []): Traversable|array
    {
        $results = [];

        foreach($keys as $key) {
            $results[$key] = $this->getItem($key);
        }

        return $results;
    }

    public function hasItem($key): bool
    {
        $this->sanityCheckKey($key);

        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        $this->deferredItems = [];
        return $this->doFileCache->flush();
    }

    public function deleteItem($key): bool
    {
        $this->sanityCheckKey($key);

        if (array_key_exists($key, $this->deferredItems)) {
            unset($this->deferredItems[$key]);
            return true;
        }

        $this->doFileCache->delete($key);

        return true;
    }

    public function deleteItems(array $keys): bool
    {


        foreach($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->doFileCache->set($item->getKey(), $item->get(), $item->getExpires());
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferredItems[$item->getKey()] = $item->prepareForSaveDeferred();
        return true;
    }

    public function commit(): bool
    {
        foreach($this->deferredItems as $item) {
            $this->save($item);
        }
        $this->deferredItems = [];
        return true;
    }

    public function __destruct()
    {
        $this->commit();
    }
}