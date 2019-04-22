<?php

namespace MauticPlugin\MauticMediaBundle\Tests\Report;

use PHPUnit\Framework\TestCase;
use MauticPlugin\MauticMediaBundle\Report\DatePadder;

/**
 * Test class to test DatePadder, there should be 'technically' be tests for
 * every time interval, but I'm lazy...
 */
class DatePadderTest extends TestCase
{
    public function setUp()
    {
        $this->dateFrom = new \DateTime('2019-03-01 00:00:00');
        $this->dateTo = new \DateTime('2019-03-01 23:59:59');
        parent::setUp();
    }

    /**
     * @var \DateTime
     */
    private $dateFrom;

    /**
     * @var \DateTime
     */
    private $dateTo;


    /** @test */
    public function it_pads_a_report_by_hours()
    {
        $report = [
            [ 'label' => '2019-03-01 23:00', 'cost' => 5.99 ],
            [ 'label' => '2019-03-01 05:00', 'cost' => 24.99 ],
            [ 'label' => '2019-03-01 06:00', 'cost' => 24.99 ],
            [ 'label' => '2019-03-01 02:00', 'cost' => 24.99 ],
        ];

        $expectedResult = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = ($i >= 10) ? $i : "0" . $i;
            $date = "2019-03-01 {$hour}:00";
            $cost = 0;
            foreach ($report as $key => $row) {
                if ($row['label'] == $date) {
                    $cost = $row['cost'];
                }
            }
            $expectedResult[] = [
                'label' => $date,
                'cost' => $cost,
            ];
        }

        $padder = new DatePadder($report, 'label', 'H');
        $padded = $padder->pad($this->dateFrom, $this->dateTo);

        $this->assertEquals($padded, $expectedResult);
    }
}
