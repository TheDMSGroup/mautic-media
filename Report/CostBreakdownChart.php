<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Report;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\ORM\EntityManager;
use MauticPlugin\MauticMediaBundle\Entity\StatRepository;

class CostBreakdownChart
{
    /**
     * Pretty colors ooooooo.
     *
     * @var array
     */
    private $providerColors = [
        'facebook' => [
            'backgroundColor'           => 'rgba(59,89,153 ,0.5)',
            'borderColor'               => 'rgba(59,89,153 ,1)',
            'pointHoverBackgroundColor' => 'rgba(59,89,153 ,0.5)',
            'pointHoverBorderColor'     => 'rgba(59,89,153 ,0.5)',
        ],
        'snapchat' => [
            'backgroundColor'           => 'rgba(255,252,0 ,0.5)',
            'borderColor'               => 'rgba(255,205,0, 1)',
            'pointHoverBackgroundColor' => 'rgba(255,252,0 ,0.5)',
            'pointHoverBorderColor'     => 'rgba(255,252,0 ,0.5)',
        ],
        'bing' => [
            'backgroundColor'           => 'rgba(0, 255, 0, 0.5)',
            'borderColor'               => 'rgba(0, 144, 0, 1)',
            'pointHoverBackgroundColor' => 'rgba(0, 255, 0, 0.5)',
            'pointHoverBorderColor'     => 'rgba(0, 255, 0, 0.5)',
        ],
        'google' => [
            'backgroundColor'           => 'rgba(221,75,57 ,0.5)',
            'borderColor'               => 'rgba(221,75,57 ,1)',
            'pointHoverBackgroundColor' => 'rgba(221,75,57 ,0.5)',
            'pointHoverBorderColor'     => 'rgba(221,75,57 ,0.5)',
        ],
    ];

    /**
     * @var CostBreakdownReporter
     */
    private $reporter;

    /**
     * CostBreakdownChart's constructor.
     *
     * @param StatRepository     $statRepository
     * @param EntityManager      $em
     * @param CacheProvider|null $cache
     */
    public function __construct(CostBreakdownReporter $reporter)
    {
        $this->reporter = $reporter;
    }

    /**
     * @param int       $campaignId
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array
     */
    public function getChart($campaignId, $dateFrom, $dateTo)
    {
        $report = $this->reporter->getReport($campaignId, $dateFrom, $dateTo);

        // Since the default report has all the providers in one array, we need
        // to group and separate based on the provider so we can properly pad
        // the report.
        $providers = [];
        foreach ($report as $row) {
            if (!isset($providers[$row['provider']])) {
                $providers[$row['provider']] = [];
            }
            $providers[$row['provider']][] = $row;
        }

        foreach ($providers as $key => $provider) {
            $timeInterval    = DatePadder::getTimeUnitFromDateRange($dateFrom, $dateTo);
            $providers[$key] = (new DatePadder($provider, 'date_time', $timeInterval))->pad($dateFrom, $dateTo);
        }

        // We can just pull the first provider to get it's date_time to use to
        // match up the report.
        $labels   = array_column(reset($providers), 'date_time');
        $datasets = [];
        foreach ($providers as $name => $provider) {
            // Transform the report to be chart readable.
            $datasets[$name] = [
                'label' => ucfirst($name),
                'data'  => array_column($provider, 'spend'),
            ];
            // If they have custom colors set, apply it.
            if (isset($this->providerColors[$row['provider']])) {
                $datasets[$name] = array_merge($datasets[$name], $this->providerColors[$name]);
            }
        }

        $report = [
            'labels'   => $labels,
            'datasets' => array_values($datasets),
        ];

        return $report;
    }
}
