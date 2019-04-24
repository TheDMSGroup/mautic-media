<?php

namespace MauticPlugin\MauticMediaBundle\Report;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Gets dates from the given request and session, then sets the dates to session.
 * Used for reporting.
 */
class Dates
{
    /**
     * @var \DateTime
     */
    private $dateFrom;

    /**
     * @var \DateTime
     */
    private $dateTo;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Session
     */
    private $session;

    /**
     * ReportHelper constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request, Session $session)
    {
        $this->request = $request;
        $this->session = $session;
        $this->getDatesFromRequest();
    }

    /**
     * Gets dates from the request or session.
     */
    private function getDatesFromRequest()
    {
        $postDateRange = $this->request->get('daterange', []); // POST vars
        if (empty($postDateRange)) {
            /** @var \DateTime[] $dateRange */
            $sessionDateFrom = $this->session->get('mautic.daterange.form.from'); // session Vars
            $sessionDateTo   = $this->session->get('mautic.daterange.form.to');
            if (!empty($sessionDateFrom) && !empty($sessionDateTo)) {
                $dateFrom = new \DateTime($sessionDateFrom);
                $dateTo   = new \DateTime($sessionDateTo);
            }
        } else {
            // convert POST strings to DateTime Objects
            $dateFrom = new \DateTime($postDateRange['date_from']);
            $dateTo   = new \DateTime($postDateRange['date_to']);
            $this->session->set('mautic.daterange.form.from', $postDateRange['date_from']);
            $this->session->set('mautic.daterange.form.to', $postDateRange['date_to']);
        }

        $dateFrom->setTime(0, 0, 0);
        $dateTo->setTime(23, 59, 59);

        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    /**
     * @return \DateTime
     */
    public function getFrom()
    {
        return $this->dateFrom;
    }

    /**
     * @return \DateTime
     */
    public function getTo()
    {
        return $this->dateTo;
    }
}
