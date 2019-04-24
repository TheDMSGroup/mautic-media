<?php

namespace MauticPlugin\MauticMediaBundle\Report;

/**
 * Pads out dates in a report's result to match up with other charts.
 */
class DatePadder
{
    /** @var array */
    private $intervalMap = [
            'H' => ['hour', 'Y-m-d H:00'],
            'd' => ['day', 'Y-m-d'],
            'W' => ['week', 'Y \w\e\e\k W'],
            'Y' => ['year', 'Y'],
            'm' => ['minute', 'Y-m-d H:i'],
            's' => ['second', 'Y-m-d H:i:s'],
        ];

    /**
     * The report to pad out.
     *
     * @var array
     */
    private $report;

    /**
     * The array key.
     *
     * @var string
     */
    private $dateKey;

    /**
     * The time unit used to pad the report.
     *
     * @var string
     */
    private $timeUnit;

    /**
     * @param array  $report
     * @param string $dateKey  The key in the $report array that contains the
     * @param string $timeUnit
     *                         dates
     */
    public function __construct($report, $dateKey, $timeUnit)
    {
        $this->report   = $report;
        $this->dateKey  = $dateKey;
        $this->timeUnit = $timeUnit;
    }

    /**
     * @param \DateTime           $dateFrom
     *                                      $param \DateTime $dateTo
     * @param \Closure|array|null $filler   zeroed out array to use as the padding
     *
     * @return array
     */
    public function pad($dateFrom, $dateTo, $filler = null)
    {
        // Sort and pad-out the results to match the other charts.
        $interval    = \DateInterval::createFromDateString('1 '.$this->intervalMap[$this->timeUnit][0]);
        $periods     = new \DatePeriod($dateFrom, $interval, $dateTo);
        $updatedData = [];

        if (is_null($filler)) {
            $filler = reset($this->report);
            foreach ($filler as $key => $row) {
                if ($key !== $this->dateKey) {
                    $filler[$key] = 0;
                }
            }
        }

        foreach ($periods as $period) {
            $dateToCheck   = $period->format($this->intervalMap[$this->timeUnit][1]);
            $dataKey       = array_search($dateToCheck, array_column($this->report, $this->dateKey));

            $updatedData[] = array_replace(
                (false !== $dataKey) ? $this->report[$dataKey] : $filler,
                [$this->dateKey => $dateToCheck]
                );
        }

        return $updatedData;
    }

    /**
     * Returns appropriate time unit from a date range so the line/bar charts won't be too full/empty.
     *
     * @param $dateFrom
     * @param $dateTo
     *
     * @return string
     */
    public static function getTimeUnitFromDateRange($dateFrom, $dateTo)
    {
        $dayDiff = $dateTo->diff($dateFrom)->format('%a');
        $unit    = 'd';

        if ($dayDiff <= 1) {
            $unit = 'H';

            $sameDay    = $dateTo->format('d') == $dateFrom->format('d') ? 1 : 0;
            $hourDiff   = $dateTo->diff($dateFrom)->format('%h');
            $minuteDiff = $dateTo->diff($dateFrom)->format('%i');
            if ($sameDay && !intval($hourDiff) && intval($minuteDiff)) {
                $unit = 'i';
            }
            $secondDiff = $dateTo->diff($dateFrom)->format('%s');
            if (!intval($minuteDiff) && intval($secondDiff)) {
                $unit = 'i';
            }
        }
        if ($dayDiff > 31) {
            $unit = 'W';
        }
        if ($dayDiff > 100) {
            $unit = 'm';
        }
        if ($dayDiff > 1000) {
            $unit = 'Y';
        }

        return $unit;
    }
}
