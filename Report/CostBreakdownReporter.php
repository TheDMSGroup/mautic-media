<?php

namespace MauticPlugin\MauticMediaBundle\Report;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use MauticPlugin\MauticMediaBundle\Entity\StatRepository;

class CostBreakdownReporter
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var CacheProvider
     */
    private $cache;

    /**
     * CostBreakdownReport's constructor.
     *
     * @param EntityManager $em
     * @param CacheProvider  $cache
     */
    public function __construct($em, $cache = null)
    {
        $this->em = $em;
        $this->cache = $cache;
    }

    /**
     * Get the report with caching if available.
     *
     * @param int       $campaignId The campaign's ID
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     */
    public function getReport($campaignId, $dateFrom, $dateTo)
    {
        $timeInterval     = DatePadder::getTimeUnitFromDateRange($dateFrom, $dateTo);
        $chartQueryHelper = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo, $timeInterval);
        $dbTimeInterval   = $chartQueryHelper->translateTimeUnit($timeInterval);
        $qb               = $this->em->getRepository('MauticMediaBundle:Stat')->getProviderCostBreakdown(
            $campaignId,
            $dateFrom,
            $dateTo,
            $timeInterval,
            $dbTimeInterval
        );

        if (isset($this->cache) && $this->cache instanceof CacheProvider) {
            $qb = $qb->getConnection()->executeCacheQuery(
                $qb->getSQL(),
                $qb->getParameters(),
                $qb->getParameterTypes(),
                new QueryCacheProfile(900, 'media_cost_breakdown_query', $this->cache)
            );
        } else {
            $qb = $qb->execute();
        }
        $report = $qb->fetchAll();

        return $report;
    }
}
