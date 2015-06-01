<?php

namespace Spatie\Analytics;

interface Cache
{
    /**
     * Determine whether the cache contains an item
     * 
     * @param  string $key
     * @return bool
     */
    public function has($key);

    /**
     * Retrieve an item from the cache
     * 
     * @param  string $key
     * @return mixed
     */
    public function get($key);

    /**
     * Put an item in the cache
     * 
     * @param  string $key
     * @param  mixed $contents
     * @param  int $lifetime  The item's lifetime in seconds
     * @return void
     */
    public function put($key, $contents, $lifetime);
}
