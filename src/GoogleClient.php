<?php

namespace Spatie\Analytics;

use Carbon\Carbon;
use Exception;
use Google_Client;
use Google_Service_Analytics;

class GoogleClient
{
    /**
     * @var Google_Service_Analytics
     */
    protected $service;

    /**
     * @var \Spatie\Analytics\Cache
     */
    protected $cache;

    /**
     * @var int
     */
    protected $cacheLifeTimeInMinutes;

    /**
     * @var int
     */
    protected $realTimeCacheLifeTimeInSeconds;

    /**
     * @param Google_Client           $client
     * @param \Spatie\Analytics\Cache $cache
     * @param int                     $cacheLifeTimeInMinutes
     * @param int                     $realTimeCacheLifeTimeInSeconds
     */
    public function __construct(
        Google_Client $client,
        Cache $cache = null,
        $cacheLifeTimeInMinutes = 0,
        $realTimeCacheLifeTimeInSeconds = 0
    ) {
        $this->service = new Google_Service_Analytics($client);
        $this->cache = $cache;

        $this->cacheLifeTimeInMinutes = $cacheLifeTimeInMinutes;
        $this->realTimeCacheLifeTimeInSeconds = $realTimeCacheLifeTimeInSeconds;
    }

    /**
     * Query the Google Analytics Service with given parameters.
     *
     * @param int    $id
     * @param string $startDate
     * @param string $endDate
     * @param string $metrics
     * @param array  $others
     *
     * @return mixed
     */
    public function performQuery($id, $startDate, $endDate, $metrics, array $others = [])
    {
        $cacheName = $this->determineCacheName(func_get_args());

        if ($this->useCache() && $this->cache->has($cacheName)) {
            return $this->cache->get($cacheName);
        }

        $googleAnswer = $this->service->data_ga->get($id, $startDate, $endDate, $metrics, $others);

        if ($this->useCache()) {
            $this->cache->put($cacheName, $googleAnswer, $this->cacheLifeTimeInMinutes);
        }

        return $googleAnswer;
    }

    /**
     * Query the Google Analytics Real Time Reporting Service with given parameters.
     *
     * @param int    $id
     * @param string $metrics
     * @param array  $others
     *
     * @return mixed
     */
    public function performRealTimeQuery($id, $metrics, array $others = [])
    {
        $realTimeCacheName = $this->determineRealTimeCacheName(func_get_args());

        if ($this->useRealTimeCache() && $this->cache->has($realTimeCacheName)) {
            return $this->cache->get($realTimeCacheName);
        }

        $googleAnswer = $this->service->data_realtime->get($id, $metrics, $others);

        if ($this->useRealTimeCache()) {
            $this->cache->put(
                $realTimeCacheName,
                $googleAnswer,
                Carbon::now()->addSeconds($this->realTimeCacheLifeTimeInSeconds)
            );
        }

        return $googleAnswer;
    }

    /**
     * Get a site Id by its URL.
     *
     * @param string $url
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function getSiteIdByUrl($url)
    {
        $siteIds = $this->getAllSiteIds();

        if (isset($siteIds[$url])) {
            return $siteIds[$url];
        }

        throw new Exception("Site $url is not present in your Analytics account.");
    }

    /**
     * Get all siteIds.
     *
     * @return array
     */
    public function getAllSiteIds()
    {
        static $siteIds = null;

        if (!is_null($siteIds)) {
            return $siteIds;
        }

        foreach ($this->service->management_profiles->listManagementProfiles('~all', '~all') as $site) {
            $siteIds[$site['websiteUrl']] = 'ga:'.$site['id'];
        }

        return $siteIds;
    }

    /**
     * Determine the cache name for the set of query properties given.
     *
     * @param array $properties
     *
     * @return string
     */
    protected function determineCacheName(array $properties)
    {
        return 'spatie.laravel-analytics.'.md5(serialize($properties));
    }

    /**
     * Determine if request to Google should be cached.
     *
     * @return bool
     */
    protected function useCache()
    {
        return isset($this->cache) && $this->cacheLifeTimeInMinutes > 0;
    }

    /**
     * Set the cache time.
     *
     * @param int $cacheLifeTimeInMinutes
     *
     * @return self
     */
    public function setCacheLifeTimeInMinutes($cacheLifeTimeInMinutes)
    {
        $this->cacheLifeTimeInMinutes = $cacheLifeTimeInMinutes;

        return $this;
    }

    /**
     * Determine the cache name for RealTime function calls for the set of query properties given.
     *
     * @param array $properties
     *
     * @return string
     */
    protected function determineRealTimeCacheName(array $properties)
    {
        return 'spatie.laravel-analytics.RealTime.'.md5(serialize($properties));
    }

    /**
     * Determine if RealTime request to Google should be cached.
     *
     * @return bool
     */
    protected function useRealTimeCache()
    {
        return isset($this->cache) && $this->realTimeCacheLifeTimeInSeconds > 0;
    }

    /**
     * Set the cache time.
     *
     * @param int $realTimeCacheLifeTimeInSeconds
     *
     * @return self
     */
    public function setRealTimeCacheLifeTimeInMinutes($realTimeCacheLifeTimeInSeconds)
    {
        $this->realTimeCacheLifeTimeInSeconds = $realTimeCacheLifeTimeInSeconds;

        return $this;
    }
}
