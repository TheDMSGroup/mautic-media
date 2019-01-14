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
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Helper\TemplatingHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Model\LeadModel as ContactModel;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\StatRepository;
use MauticPlugin\MauticMediaBundle\Event\MediaAccountEvent;
use MauticPlugin\MauticMediaBundle\Helper\BingHelper;
use MauticPlugin\MauticMediaBundle\Helper\CampaignSettingsHelper;
use MauticPlugin\MauticMediaBundle\Helper\FacebookHelper;
use MauticPlugin\MauticMediaBundle\Helper\GoogleHelper;
use MauticPlugin\MauticMediaBundle\Helper\SnapchatHelper;
use MauticPlugin\MauticMediaBundle\MediaEvents;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

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

    /** @var array */
    protected $campaignNames;

    /** @var CampaignModel */
    protected $campaignModel;

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
        ContactModel $contactModel,
        CampaignModel $campaignModel
    ) {
        $this->formModel      = $formModel;
        $this->trackableModel = $trackableModel;
        $this->templating     = $templating;
        $this->dispatcher     = $dispatcher;
        $this->contactModel   = $contactModel;
        $this->campaignModel  = $campaignModel;
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

        $params = [
            'media_account_id' => $MediaAccount->getId(),
            'provider'         => $MediaAccount->getProvider(),
        ];

        if ($campaignId) {
            $params['campaign_id'] = $campaignId;
        }

        $providerAccounts = $this->getStatRepository()->getProviderAccounts(
            $MediaAccount->getId(),
            $MediaAccount->getProvider()
        );

        $totals = [];
        foreach ($providerAccounts as $providerAccountId => $providerAccountName) {
            $params['provider_account_id'] = $providerAccountId;
            $q                             = $query->prepareTimeDataQuery(
                'media_account_stats',
                'date_added',
                $params,
                'spend',
                'sum'
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
            if (!$totals) {
                $totals = $data;
            }
            foreach ($data as $key => $val) {
                $totals[$key] += $val;
            }
            foreach ($data as $key => $val) {
                if (0 !== $val) {
                    $chart->setDataset($providerAccountName, $data);
                    break;
                }
            }
        }
        foreach ($totals as $val) {
            if (0 !== $val) {
                $chart->setDataset(
                    $this->translator->trans('mautic.media.form.provider.total.'.$MediaAccount->getProvider()),
                    $totals
                );
                break;
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
     * @param MediaAccount|null $mediaAccount
     * @param \DateTime         $dateFrom
     * @param \DateTime         $dateTo
     * @param OutputInterface   $output
     *
     * @throws \Exception
     */
    public function pullData(
        MediaAccount $mediaAccount = null,
        \DateTime $dateFrom,
        \DateTime $dateTo,
        OutputInterface $output
    ) {
        if (!$mediaAccount) {
            return;
        }
        $mediaAccountId       = $mediaAccount->getId();
        $providerAccountId    = $mediaAccount->getAccountId();
        $providerClientId     = $mediaAccount->getClientId();
        $providerClientSecret = $mediaAccount->getClientSecret();
        $providerToken        = $mediaAccount->getToken();

        $data                   = $this->getStatRepository()->getProviderAccountsWithCampaigns(
            $mediaAccountId,
            $mediaAccount->getProvider()
        );
        $campaignNames          = $this->getCampaignNames();
        $campaignSettings       = $mediaAccount->getCampaignSettings();
        $campaignSettingsHelper = new CampaignSettingsHelper(
            $campaignNames,
            $campaignSettings,
            $data
        );
        switch ($mediaAccount->getProvider()) {
            case MediaAccount::PROVIDER_FACEBOOK:
                $helper = new FacebookHelper(
                    $mediaAccountId,
                    $providerAccountId,
                    $providerClientId,
                    $providerClientSecret,
                    $providerToken,
                    $output,
                    $this->em,
                    $campaignSettingsHelper
                );
                $helper->pullData($dateFrom, $dateTo);
                break;

            case MediaAccount::PROVIDER_BING:
                $helper = new BingHelper();
                break;

            case MediaAccount::PROVIDER_GOOGLE:
                $helper = new GoogleHelper(
                    $mediaAccountId,
                    $providerAccountId,
                    $providerClientId,
                    $providerClientSecret,
                    $providerToken,
                    $output,
                    $this->em,
                    $campaignSettingsHelper
                );
                $helper->pullData($dateFrom, $dateTo);
                break;

            case MediaAccount::PROVIDER_SNAPCHAT:
                $helper = new SnapchatHelper();
                break;
        }
    }

    /**
     * Get the campaign names for correlating external provider accounts/campaigns in the cron task.
     */
    private function getCampaignNames()
    {
        if (null === $this->campaignNames) {
            $this->campaignNames = [];
            $campaignRepository  = $this->campaignModel->getRepository();
            $campaigns           = $campaignRepository->getEntities(
                [
                    'orderBy'    => 'c.name',
                    'orderByDir' => 'ASC',
                ]
            );
            foreach ($campaigns as $campaign) {
                $id        = $campaign->getId();
                $published = $campaign->isPublished();
                $name      = $campaign->getName();
                // Adding periods to the end such that an unpublished campaign will be less likely to match against
                // a published campaign of the same name.
                $this->campaignNames[$id] = htmlspecialchars_decode($name).(!$published ? '.' : '');
            }
        }

        return $this->campaignNames;
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
