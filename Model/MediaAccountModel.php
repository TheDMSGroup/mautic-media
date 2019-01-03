<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Model;

use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Helper\TemplatingHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Model\LeadModel as ContactModel;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\Stat;
use MauticPlugin\MauticMediaBundle\Entity\StatRepository;
use MauticPlugin\MauticMediaBundle\Event\MediaAccountEvent;
use MauticPlugin\MauticMediaBundle\Helper\BingHelper;
use MauticPlugin\MauticMediaBundle\Helper\FacebookHelper;
use MauticPlugin\MauticMediaBundle\Helper\GoogleHelper;
use MauticPlugin\MauticMediaBundle\MediaEvents;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

// use MauticPlugin\MauticMediaBundle\MediaAccountEvents;
// use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
// use MauticPlugin\MauticMediaBundle\Entity\Event as EventEntity;
// use MauticPlugin\MauticMediaBundle\Entity\Stat;
// use MauticPlugin\MauticMediaBundle\Event\MediaAccountEvent;
// use MauticPlugin\MauticMediaBundle\Event\MediaAccountStatEvent;
// use MauticPlugin\MauticMediaBundle\Event\MediaAccountTimelineEvent;

/**
 * Class MediaAccountModel.
 */
class MediaAccountModel extends FormModel
{
    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var FormModel */
    protected $formModel;

    /** @var TrackableModel */
    protected $trackableModel;

    /** @var TemplatingHelper */
    protected $templating;

    /** @var ContactModel */
    protected $contactModel;

    /**
     * MediaAccountModel constructor.
     *
     * @param \Mautic\FormBundle\Model\FormModel $formModel
     * @param TrackableModel                     $trackableModel
     * @param TemplatingHelper                   $templating
     * @param EventDispatcherInterface           $dispatcher
     * @param ContactModel                       $contactModel
     */
    public function __construct(
        \Mautic\FormBundle\Model\FormModel $formModel,
        TrackableModel $trackableModel,
        TemplatingHelper $templating,
        EventDispatcherInterface $dispatcher,
        ContactModel $contactModel
    ) {
        $this->formModel      = $formModel;
        $this->trackableModel = $trackableModel;
        $this->templating     = $templating;
        $this->dispatcher     = $dispatcher;
        $this->contactModel   = $contactModel;
    }

    /**
     * @return string
     */
    public function getActionRouteBase()
    {
        return 'MediaAccount';
    }

    /**
     * @return string
     */
    public function getPermissionBase()
    {
        return 'plugin:media:items';
    }

    /**
     * {@inheritdoc}
     *
     * @param object                              $entity
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param string                              $action
     * @param array                               $options
     *
     * @throws NotFoundHttpException
     */
    public function createForm($entity, $formFactory, $action = null, $options = [])
    {
        if (!$entity instanceof MediaAccount) {
            throw new MethodNotAllowedHttpException(['MediaAccount']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        // Prevent clone action from complaining about extra fields.
        $options['allow_extra_fields'] = true;

        return $formFactory->create('media', $entity, $options);
    }

    /**
     * @param null $id
     *
     * @return MediaAccount|null|object
     */
    public function getEntity($id = null)
    {
        if (null === $id) {
            return new MediaAccount();
        }

        return parent::getEntity($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param MediaAccount $entity
     * @param bool|false   $unlock
     */
    public function saveEntity($entity, $unlock = true)
    {
        parent::saveEntity($entity, $unlock);

        $this->getRepository()->saveEntity($entity);
    }

    /**
     * {@inheritdoc}
     *
     * @return \MauticPlugin\MauticMediaBundle\Entity\MediaAccountRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository('MauticMediaBundle:MediaAccount');
    }

    /**
     * {@inheritdoc}
     *
     * @return \MauticPlugin\MauticMediaBundle\Entity\StatRepository
     */
    public function getEventRepository()
    {
        return $this->em->getRepository('MauticMediaBundle:Event');
    }

    /**
     * @param MediaAccount   $MediaAccount
     * @param                $unit
     * @param \DateTime|null $dateFrom
     * @param \DateTime|null $dateTo
     * @param null           $campaignId
     * @param null           $dateFormat
     * @param bool           $canViewOthers
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getStats(
        MediaAccount $MediaAccount,
        $unit,
        \DateTime $dateFrom = null,
        \DateTime $dateTo = null,
        $campaignId = null,
        $dateFormat = null,
        $canViewOthers = true
    ) {
        $query = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo, $unit);
        $unit  = (null === $unit) ? $this->getTimeUnitFromDateRange($dateFrom, $dateTo) : $unit;
        $chart = new LineChart($unit, $dateFrom, $dateTo, $dateFormat);
        $stat  = new Stat();

        $params = ['media_account_id' => $MediaAccount->getId()];

        if ($campaignId) {
            $params['campaign_id'] = $campaignId;
        }

        foreach ($stat->getAllTypes() as $type) {
            $params['type'] = $type;
            $q              = $query->prepareTimeDataQuery(
                'media_account_stats',
                'date_added',
                $params
            );

            if (!in_array($unit, ['H', 'i', 's'])) {
                // For some reason, Mautic only sets UTC in Query Date builder
                // if its an intra-day date range ¯\_(ツ)_/¯
                // so we have to do it here.
                $userTZ        = new \DateTime('now');
                $userTzName    = $userTZ->getTimezone()->getName();
                $paramDateTo   = $q->getParameter('dateTo');
                $paramDateFrom = $q->getParameter('dateFrom');
                $paramDateTo   = new \DateTime($paramDateTo);
                $paramDateTo->setTimeZone(new \DateTimeZone('UTC'));
                $q->setParameter('dateTo', $paramDateTo->format('Y-m-d H:i:s'));
                $paramDateFrom = new \DateTime($paramDateFrom);
                $paramDateFrom->setTimeZone(new \DateTimeZone('UTC'));
                $q->setParameter('dateFrom', $paramDateFrom->format('Y-m-d H:i:s'));
                $select    = $q->getQueryPart('select')[0];
                $newSelect = str_replace(
                    't.date_added,',
                    "CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'),",
                    $select
                );
                $q->resetQueryPart('select');
                $q->select($newSelect);

                // AND adjust the group By, since its using db timezone Date values
                $groupBy    = $q->getQueryPart('groupBy')[0];
                $newGroupBy = str_replace(
                    't.date_added,',
                    "CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'),",
                    $groupBy
                );
                $q->resetQueryPart('groupBy');
                $q->groupBy($newGroupBy);
            }

            if (!$canViewOthers) {
                $this->limitQueryToCreator($q);
            }

            $data = $query->loadAndBuildTimeData($q);
            foreach ($data as $val) {
                if (0 !== $val) {
                    $chart->setDataset($this->translator->trans('mautic.MediaAccount.graph.'.$type), $data);
                    break;
                }
            }
        }

        return $chart->render();
    }

    /**
     * Returns appropriate time unit from a date range so the line/bar charts won't be too full/empty.
     *
     * @param $dateFrom
     * @param $dateTo
     *
     * @return string
     */
    public function getTimeUnitFromDateRange($dateFrom, $dateTo)
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
                $unit = 'm';
            }
        }
        if ($dayDiff > 31) {
            $unit = 'W';
        }
        if ($dayDiff > 63) {
            $unit = 'm';
        }
        if ($dayDiff > 1000) {
            $unit = 'Y';
        }

        return $unit;
    }

    /**
     * Joins the email table and limits created_by to currently logged in user.
     *
     * @param QueryBuilder $q
     */
    public function limitQueryToCreator(QueryBuilder $q)
    {
        $q->join('t', MAUTIC_TABLE_PREFIX.'MediaAccount', 'm', 'e.id = t.media_account_id')
            ->andWhere('m.created_by = :userId')
            ->setParameter('userId', $this->userHelper->getUser()->getId());
    }

    /**
     * @param MediaAccount   $MediaAccount
     * @param                $unit
     * @param                $type
     * @param \DateTime|null $dateFrom
     * @param \DateTime|null $dateTo
     * @param null           $campaignId
     * @param null           $dateFormat
     * @param bool           $canViewOthers
     *
     * @return array
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function getStatsBySource(
        MediaAccount $MediaAccount,
        $unit,
        $type,
        \DateTime $dateFrom = null,
        \DateTime $dateTo = null,
        $campaignId = null,
        $dateFormat = null,
        $canViewOthers = true
    ) {
        $unit           = (null === $unit) ? $this->getTimeUnitFromDateRange($dateFrom, $dateTo) : $unit;
        $dateToAdjusted = clone $dateTo;
        $dateToAdjusted->setTime(23, 59, 59);
        $chart      = new LineChart($unit, $dateFrom, $dateToAdjusted, $dateFormat);
        $query      = new ChartQuery($this->em->getConnection(), $dateFrom, $dateToAdjusted, $unit);
        $utmSources = $this->getStatRepository()->getSourcesByMediaAccount(
            $MediaAccount->getId(),
            $dateFrom,
            $dateToAdjusted
        );

        //if (isset($campaignId)) {
        if (!empty($campaignId)) {
            $params['campaign_id'] = (int) $campaignId;
        }
        $params['media_account_id'] = $MediaAccount->getId();

        $userTZ     = new \DateTime('now');
        $userTzName = $userTZ->getTimezone()->getName();

        if ('revenue' != $type) {
            $params['type'] = $type;
            foreach ($utmSources as $utmSource) {
                $params['utm_source'] = empty($utmSource) ? ['expression' => 'isNull'] : $utmSource;
                $q                    = $query->prepareTimeDataQuery(
                    'media_account_stats',
                    'date_added',
                    $params
                );

                if (!in_array($unit, ['H', 'i', 's'])) {
                    // For some reason, Mautic only sets UTC in Query Date builder
                    // if its an intra-day date range ¯\_(ツ)_/¯
                    // so we have to do it here.
                    $paramDateTo   = $q->getParameter('dateTo');
                    $paramDateFrom = $q->getParameter('dateFrom');
                    $paramDateTo   = new \DateTime($paramDateTo);
                    $paramDateTo->setTimeZone(new \DateTimeZone('UTC'));
                    $q->setParameter('dateTo', $paramDateTo->format('Y-m-d H:i:s'));
                    $paramDateFrom = new \DateTime($paramDateFrom);
                    $paramDateFrom->setTimeZone(new \DateTimeZone('UTC'));
                    $q->setParameter('dateFrom', $paramDateFrom->format('Y-m-d H:i:s'));
                    $select    = $q->getQueryPart('select')[0];
                    $newSelect = str_replace(
                        't.date_added,',
                        "CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'),",
                        $select
                    );
                    $q->resetQueryPart('select');
                    $q->select($newSelect);

                    // AND adjust the group By, since its using db timezone Date values
                    $groupBy    = $q->getQueryPart('groupBy')[0];
                    $newGroupBy = str_replace(
                        't.date_added,',
                        "CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'),",
                        $groupBy
                    );
                    $q->resetQueryPart('groupBy');
                    $q->groupBy($newGroupBy);
                }
                if (!$canViewOthers) {
                    $this->limitQueryToCreator($q);
                }
                $data = $query->loadAndBuildTimeData($q);
                foreach ($data as $val) {
                    if (0 !== $val) {
                        if (empty($utmSource)) {
                            $utmSource = 'No Source';
                        }
                        $chart->setDataset($utmSource, $data);
                        break;
                    }
                }
            }
        } else {
            $params['type'] = Stat::TYPE_CONVERTED;
            // Add attribution to the chart.
            $q = $query->prepareTimeDataQuery(
                'media_account_stats',
                'date_added',
                $params
            );

            if (!$canViewOthers) {
                $this->limitQueryToCreator($q);
            }
            $dbUnit        = $query->getTimeUnitFromDateRange($dateFrom, $dateTo);
            $dbUnit        = $query->translateTimeUnit($dbUnit);
            $dateConstruct = "DATE_FORMAT(CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'), '$dbUnit.')";
            foreach ($utmSources as $utmSource) {
                $q->select($dateConstruct.' AS date, ROUND(SUM(t.attribution), 2) AS count')
                    ->groupBy($dateConstruct);
                if (empty($utmSource)) { // utmSource can be a NULL value
                    $q->andWhere('utm_source IS NULL');
                } else {
                    $q->andWhere('utm_source = :utmSource')
                        ->setParameter('utmSource', $utmSource);
                }

                $data = $query->loadAndBuildTimeData($q);
                foreach ($data as $val) {
                    if (0 !== $val) {
                        if (empty($utmSource)) {
                            $utmSource = 'No Source';
                        }
                        $chart->setDataset($utmSource, $data);
                        break;
                    }
                }
            }
        }

        return $chart->render();
    }

    /**
     * @return StatRepository
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function getStatRepository()
    {
        if (!$this->em->isOpen()) {
            $this->em = $this->em->create(
                $this->em->getConnection(),
                $this->em->getConfiguration(),
                $this->em->getEventManager()
            );
        }

        return $this->em->getRepository('MauticMediaBundle:Stat');
    }

    /**
     * @param MediaAccount|null $mediaAccount
     * @param OutputInterface   $output
     *
     * @throws \Exception
     */
    public function pullData(MediaAccount $mediaAccount = null, OutputInterface $output)
    {
        if (!$mediaAccount) {
            return;
        }
        $stats                = [];
        $dateFrom             = new \DateTime('-30 days');
        $dateTo               = new \DateTime();
        $mediaAccountId       = $mediaAccount->getId();
        $providerAccountId    = $mediaAccount->getAccountId();
        $providerClientId     = $mediaAccount->getClientId();
        $providerClientSecret = $mediaAccount->getClientSecret();
        $providerToken        = $mediaAccount->getToken();
        switch ($mediaAccount->getProvider()) {
            case MediaAccount::PROVIDER_FACEBOOK:
                $helper = new FacebookHelper(
                    $mediaAccountId,
                    $providerAccountId,
                    $providerClientId,
                    $providerClientSecret,
                    $providerToken,
                    $output
                );
                $stats  = $helper->pullData($dateFrom, $dateTo);
                break;

            case MediaAccount::PROVIDER_BING:
                $helper = new BingHelper();
                break;

            case MediaAccount::PROVIDER_GOOGLE:
                $helper = new GoogleHelper();
                break;

            case MediaAccount::PROVIDER_SNAPCHAT:
                $helper = new GoogleHelper();
                break;

        }
        if ($stats) {
            // @todo - Persist stat entities for each row of data.
            $this->getStatRepository()->saveEntities($stats);
        }
    }

    /**
     * @param            $action
     * @param            $entity
     * @param bool       $isNew
     * @param Event|null $event
     *
     * @return MediaAccountEvent|Event|\Symfony\Component\EventDispatcher\Event|null
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null)
    {
        if (!$entity instanceof MediaAccount) {
            throw new MethodNotAllowedHttpException(['MediaAccount']);
        }

        switch ($action) {
            case 'pre_save':
                $name = MediaEvents::PRE_SAVE;
                break;
            case 'post_save':
                $name = MediaEvents::POST_SAVE;
                break;
            case 'pre_delete':
                $name = MediaEvents::PRE_DELETE;
                break;
            case 'post_delete':
                $name = MediaEvents::POST_DELETE;
                break;
            default:
                return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new MediaAccountEvent($entity, $isNew);
                $event->setEntityManager($this->em);
            }

            $this->dispatcher->dispatch($name, $event);

            return $event;
        } else {
            return null;
        }
    }
}
