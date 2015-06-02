<?php

namespace Spatie\Analytics;

use Carbon\Carbon;
use DateTime;
use Exception;
use Google_Auth_AssertionCredentials;
use Google_Client;

class Analytics
{
    /**
     * @var \Spatie\Analytics\GoogleClient
     */
    protected $client;

    /**
     * @var string
     */
    protected $siteId;

    /**
     * @param  \Spatie\Analytics\GoogleClient $client
     * @param  string $siteId
     */
    public function __construct(GoogleClient $client, $siteId = '')
    {
        $this->client = $client;
        $this->siteId = $siteId;
    }

    /**
     * Get the amount of visitors and pageViews.
     *
     * @param  int $numberOfDays
     * @param  string $groupBy  Possible values: date, yearMonth
     * @return array
     */
    public function getVisitorsAndPageViews($numberOfDays = 365, $groupBy = 'date')
    {
        list($startDate, $endDate) = $this->calculateNumberOfDays($numberOfDays);

        return $this->getVisitorsAndPageViewsForPeriod($startDate, $endDate, $groupBy);
    }

    /**
     * Get the amount of visitors and pageviews for the given period.
     *
     * @param  \DateTime $startDate
     * @param  \DateTime $endDate
     * @param  string $groupBy  Possible values: date, yearMonth
     * @return array
     */
    public function getVisitorsAndPageViewsForPeriod(DateTime $startDate, DateTime $endDate, $groupBy = 'date')
    {
        $answer = $this->performQuery(
            $startDate,
            $endDate,
            'ga:visits,ga:pageviews',
            array('dimensions' => 'ga:'.$groupBy)
        );

        if (is_null($answer->rows)) {
            return array();
        }

        $visitorData = array();

        foreach ($answer->rows as $dateRow) {
            $visitorData[] = array(
                $groupBy    => Carbon::createFromFormat(($groupBy == 'yearMonth' ? 'Ym' : 'Ymd'), $dateRow[0]),
                'visitors'  => $dateRow[1],
                'pageViews' => $dateRow[2]
            );
        }

        return $visitorData;
    }

    /**
     * Get the top keywords.
     *
     * @param  int $numberOfDays
     * @param  int $maxResults
     * @return array
     */
    public function getTopKeywords($numberOfDays = 365, $maxResults = 30)
    {
        list($startDate, $endDate) = $this->calculateNumberOfDays($numberOfDays);

        return $this->getTopKeyWordsForPeriod($startDate, $endDate, $maxResults);
    }

    /**
     * Get the top keywords for the given period.
     *
     * @param  \DateTime $startDate
     * @param  \DateTime $endDate
     * @param  int $maxResults
     * @return array
     */
    public function getTopKeyWordsForPeriod(DateTime $startDate, DateTime $endDate, $maxResults = 30)
    {
        $answer = $this->performQuery(
            $startDate,
            $endDate,
            'ga:sessions',
            array(
                'dimensions' => 'ga:keyword',
                'sort' => '-ga:sessions',
                'max-results' => $maxResults,
                'filters' => 'ga:keyword!=(not set);ga:keyword!=(not provided)'
            )
        );

        if (is_null($answer->rows)) {
            return array();
        }

        $keywordData = array();

        foreach ($answer->rows as $pageRow) {
            $keywordData[] = array(
                'keyword' => $pageRow[0],
                'sessions' => $pageRow[1]
            );
        }

        return $keywordData;
    }

    /**
     * Get the top referrers.
     *
     * @param  int $numberOfDays
     * @param  int $maxResults
     * @return array
     */
    public function getTopReferrers($numberOfDays = 365, $maxResults = 20)
    {
        list($startDate, $endDate) = $this->calculateNumberOfDays($numberOfDays);

        return $this->getTopReferrersForPeriod($startDate, $endDate, $maxResults);
    }

    /**
     * Get the top referrers for the given period.
     *
     * @param  \DateTime $startDate
     * @param  \DateTime $endDate
     * @param  int $maxResults
     * @return array
     */
    public function getTopReferrersForPeriod(DateTime $startDate, DateTime $endDate, $maxResults)
    {
        $answer = $this->performQuery(
            $startDate,
            $endDate,
            'ga:pageviews',
            array(
                'dimensions' => 'ga:fullReferrer',
                'sort' => '-ga:pageviews',
                'max-results' => $maxResults
            )
        );

        if (is_null($answer->rows)) {
            return array();
        }

        $referrerData = array();

        foreach ($answer->rows as $pageRow) {
            $referrerData[] = array(
                'url' => $pageRow[0],
                'pageViews' => $pageRow[1]
            );
        }

        return $referrerData;
    }

    /**
     * Get the top browsers.
     *
     * @param  int $numberOfDays
     * @param  int $maxResults
     * @return array
     */
    public function getTopBrowsers($numberOfDays = 365, $maxResults = 6)
    {
        list($startDate, $endDate) = $this->calculateNumberOfDays($numberOfDays);

        return $this->getTopBrowsersForPeriod($startDate, $endDate, $maxResults);
    }

    /**
     * Get the top browsers for the given period.
     *
     * @param  \DateTime $startDate
     * @param  \DateTime $endDate
     * @param  int $maxResults
     * @return array
     */
    public function getTopBrowsersForPeriod(DateTime $startDate, DateTime $endDate, $maxResults)
    {
        $answer = $this->performQuery(
            $startDate,
            $endDate,
            'ga:sessions',
            array(
                'dimensions' => 'ga:browser',
                'sort' => '-ga:sessions'
            )
        );

        if (is_null($answer->rows)) {
            return array();
        }

        $browserData = array();

        foreach ($answer->rows as $browserRow) {
            $browserData[] = array(
                'browser' => $browserRow[0],
                'sessions' => $browserRow[1]
            );
        }

        $browserCollection = array_slice($browserData, 0, $maxResults - 1);

        if (count($browserData) > $maxResults) {
            $otherBrowsers = array_slice($browserData, $maxResults - 1);

            $sessions = array_map(function ($browser) {
                return $browser['sessions'];
            }, $otherBrowsers);

            $browserCollection[] = array(
                'browser' => 'other',
                'sessions' => array_sum($sessions)
            );
        }

        return $browserCollection;
    }

    /**
     * Get the most visited pages.
     *
     * @param  int $numberOfDays
     * @param  int $maxResults
     * @return array
     */
    public function getMostVisitedPages($numberOfDays = 365, $maxResults = 20)
    {
        list($startDate, $endDate) = $this->calculateNumberOfDays($numberOfDays);

        return $this->getMostVisitedPagesForPeriod($startDate, $endDate, $maxResults);
    }

    /**
     * Get the number of active users currently on the site
     *
     * @param  array $others
     * @return int
     */
    public function getActiveUsers($others = array())
    {
        $answer = $this->performRealTimeQuery('rt:activeUsers', $others);
    
        if (is_null($answer->rows)) {
            return 0;
        }
        
        return $answer->rows[0][0];
    }
    
    /**
     * Get the most visited pages for the given period.
     *
     * @param  \DateTime $startDate
     * @param  \DateTime $endDate
     * @param  int $maxResults
     * @return array
     */
    public function getMostVisitedPagesForPeriod(DateTime $startDate, DateTime $endDate, $maxResults = 20)
    {
        $answer = $this->performQuery(
            $startDate,
            $endDate,
            'ga:pageviews',
            array(
                'dimensions' => 'ga:pagePath',
                'sort' => '-ga:pageviews',
                'max-results' => $maxResults
            )
        );

        if (is_null($answer->rows)) {
            return array();
        }

        $pagesData = array();

        foreach ($answer->rows as $pageRow) {
            $pagesData[] = array(
                'url' => $pageRow[0],
                'pageViews' => $pageRow[1]
            );
        }

        return $pagesData;
    }

    /**
     * Returns the site id (ga:xxxxxxx) for the given url.
     *
     * @param  string $url
     * @return string
     */
    public function getSiteIdByUrl($url)
    {
        return $this->client->getSiteIdByUrl($url);
    }

    /**
     * Call the query method on the authenticated client.
     *
     * @param  \DateTime $startDate
     * @param  \DateTime $endDate
     * @param  string $metrics
     * @param  array $others
     * @return mixed
     */
    public function performQuery(DateTime $startDate, DateTime $endDate, $metrics, $others = array())
    {
        return $this->client->performQuery(
            $this->siteId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $metrics,
            $others
        );
    }

    /**
     * Call the real time query method on the authenticated client.
     *
     * @param  string $metrics
     * @param  array $others
     * @return mixed
     */
    public function performRealTimeQuery($metrics, $others = array())
    {
        return $this->client->performRealTimeQuery(
            $this->siteId,
            $metrics,
            $others
        );
    }
    
    /**
     * Return true if this site is configured to use Google Analytics.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->siteId != '';
    }

    /**
     * Returns an array with the current date and the date minus the number of days specified.
     *
     * @param  int $numberOfDays
     * @return array
     */
    protected function calculateNumberOfDays($numberOfDays)
    {
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays($numberOfDays);

        return array(
            $startDate,
            $endDate
        );
    }

    /**
     * Create a new instance via a set of parameters
     * 
     * @param  string $siteId
     *         Ex. ga:xxxxxxxx
     * @param  string $clientId
     *         Ex. xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com
     * @param  string $serviceEmail
     *         Ex. xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx@developer.gserviceaccount.com
     * @param  string $certificatePath
     *         Ex. /../keys/analytics/xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-privatekey.p12
     * 
     * @param  \Spatie\Analytics\Cache|null $cache
     * @param  int $cacheLifetimeInMinutes
     * @param  int $realTimeCacheLifetime
     * 
     * @return \Spatie\Analytics\Analytics
     * 
     * @throws \Exception
     */
    public static function create($siteId, $clientId, $serviceEmail, $certificatePath, Cache $cache = null,
        $cacheLifetimeInMinutes = 0, $realTimeCacheLifetime = 0
    ) {
        if (! file_exists($certificatePath)) {
            throw new Exception("Can't find the .p12 certificate in: $certificatePath");
        }

        $client = new Google_Client(
            array(
                'oauth2_client_id' => $clientId,
                'use_objects'      => true,
            )
        );

        $client->setAccessType('offline');

        $client->setAssertionCredentials(
            new Google_Auth_AssertionCredentials(
                $serviceEmail,
                array('https://www.googleapis.com/auth/analytics.readonly'),
                file_get_contents($certificatePath)
            )
        );

        $googleApi = new GoogleClient($client, $cache);

        $googleApi        
            ->setCacheLifeTimeInMinutes($cacheLifetimeInMinutes)
            ->setRealTimeCacheLifeTimeInMinutes($realTimeCacheLifetime);

        return new static($googleApi, $siteId);
    }
}
