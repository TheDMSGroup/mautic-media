<?php

namespace MauticPlugin\MauticMediaBundle\Report;

use MauticPlugin\MauticMediaBundle\Entity\StatRepository;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;

class CostBreakdownChart
{
    /**
     * @var StatRepository
     */
    private $repo;

    /**
     * Pretty colors ooooooo.
     * @var array
     */
    private $providerColors = [
        'facebook' => [
            'backgroundColor'           => 'rgba(59,89,153 ,1 )',
            'borderColor'               => 'rgba(59,89,153 ,1 )',
            'pointHoverBackgroundColor' => 'rgba(59,89,153 ,1 )',
            'pointHoverBorderColor'     => 'rgba(59,89,153 ,1 )',
        ],
        'snapchat' => [
            'backgroundColor'           => 'rgba(255,252,0 ,1)',
            'borderColor'               => 'rgba(255,255,255 ,1)',
            'pointHoverBackgroundColor' => 'rgba(255,252,0 ,1)',
            'pointHoverBorderColor'     => 'rgba(255,252,0 ,1)',
        ],
        'bing' => [
            'backgroundColor'           => 'rgb(12, 132, 132)',
            'borderColor'               => 'rgb(51, 51, 51)',
            'pointHoverBackgroundColor' => 'rgba(51,170,51,0.75)',
            'pointHoverBorderColor'     => 'rgba(51,170,51,1)',
        ],
        'google' => [
            'backgroundColor'           => 'rgba(51,170,51,0.1)',
            'borderColor'               => 'rgba(51,170,51,0.8)',
            'pointHoverBackgroundColor' => 'rgba(51,170,51,0.75)',
            'pointHoverBorderColor'     => 'rgba(51,170,51,1)',
        ],
    ];

    /** @var EntityManager  */
    private $em;

    /**
     * CostBreakdownReport's constructor.
     * @param StatRepository $statRepository
     * @param EntityManager $em
     */
    public function __construct($statRepository, $em)
    {
        $this->repo = $statRepository;
        $this->em = $em;
    }

    /**
     * @param int $campaignId
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array
     */
    public function getChart($campaignId, $dateFrom, $dateTo)
    {
        $timeInterval = DatePadder::getTimeUnitFromDateRange($dateFrom, $dateTo);
        $chartQueryHelper = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo, $timeInterval);
        $dbTimeInterval = $chartQueryHelper->translateTimeUnit($timeInterval);
        $report = $this->repo->getProviderCostBreakdown(
                    $campaignId,
                    $dateFrom,
                    $dateTo,
                    $timeInterval,
                    $dbTimeInterval
                );

        // Because this report is broken down into media providers, (facebook,
        // snapchat, etc). It doesn't pad properly, so we can just pull the
        // date_added column for our chart labels.
        $padded = (new DatePadder($report, 'date_added', $timeInterval))->getPaddedReport($dateFrom, $dateTo, []);
        $labels = array_column($padded, 'date_added');

        $datasets = [];
        foreach ($report as $row) {
            if (!isset($datasets[$row['provider']])) {
                $provider = [
                    'label' => ucfirst($row['provider']),
                    'data' => [],
                ];
                if (isset($this->providerColors[$row['provider']])) {
                    $provider = array_merge($provider, $this->providerColors[$row['provider']]);
                }
                $datasets[$row['provider']] = $provider;
            }

            $datasets[$row['provider']]['data'][] = $row['spend'];
        }

        return [
            'labels' => $labels,
            'datasets' => array_values($datasets),
        ];
    }
}
