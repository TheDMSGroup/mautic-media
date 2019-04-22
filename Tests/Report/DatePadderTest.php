<?php

namespace MauticPlugin\MauticMediaBundle\Tests\Report;

use PHPUnit\Framework\TestCase;
use MauticPlugin\MauticMediaBundle\Report\DatePadder;

class DatePadderTest extends TestCase
{
    public function setUp()
    {
        $this->dateFrom = new \DateTime('2019-03-01 00:00:00');
        $this->dateTo = new \DateTime('2019-03-01 23:59:59');
        parent::setUp();
    }

    /**
     * Report to be used in testing.
     * @var array
     */
    private $report = [
            [
                'label' => '2019-03-01 23:59:59',
                'cost' => 24.99
            ],
            [
                'label' => '2019-03-01 05:23:43',
                'cost' => 24.99
            ],
            [
                'label' => '2019-03-01 23:59:59',
                'cost' => 24.99
            ],
            [
                'label' => '2019-03-01 23:59:50',
                'cost' => 24.99
            ],
        ];

    /**
     * Date from to be used in testing.
     * @var \DateTime
     */
    private $dateFrom;

    /**
     * @var \DateTime
     */
    private $dateTo;


    /** @test */
    public function it_pads_a_report()
    {
        $padder = new DatePadder($this->report, 'label', 'H');

        $padded = $padder->getPaddedResults($this->dateFrom, $this->dateTo);

        var_dump($padded);
    }

    /** @test */
    public function it_organizes_a_report_by_date()
    {
    }
}
