<?php

namespace MauticPlugin\MauticMediaBundle\Report;

use MauticPlugin\MauticMediaBundle\Entity\StatRepository;

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

    /**
     * CostBreakdownReport's constructor.
     */
    public function __construct(StatRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * @param int $campaignId
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     */
    public function getChart($campaignId, $dateFrom, $dateTo)
    {
        $report = $this->repo->getProviderCostBreakdown(
                    $campaignId,
                    $dateFrom,
                    $dateTo
                );
        $report = $this->transformReportForCharts($report);
        dump($report);
    
        return $report;
    }

    /**
     * @param array $report
     */
    private function transformReportForCharts($report)
    {
        $labels = ['one','two'];
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
