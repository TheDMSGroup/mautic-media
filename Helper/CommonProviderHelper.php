<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Helper;

use Doctrine\ORM\EntityManager;
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Entity\Stat;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Interface CommonProviderHelper.
 */
class CommonProviderHelper
{
    /** @var int Number of rate limit errors after which we abort. */
    public static $rateLimitMaxErrors = 60;

    /** @var int Number of seconds to sleep between looping API operations. */
    public static $betweenOpSleep = .2;

    /** @var int Number of seconds to sleep when we hit API rate limits. */
    public static $rateLimitSleep = 60;

    public static $pageLimit = 1000;

    /** @var string */
    protected $providerAccountId;

    /** @var string */
    protected $mediaAccount;

    /** @var OutputInterface */
    protected $output;

    /** @var array */
    protected $errors = [];

    /** @var array */
    protected $stats = [];

    /** @var EntityManager */
    protected $em;

    /** @var CampaignSettingsHelper */
    protected $campaignSettingsHelper;

    /** @var string */
    protected $providerToken = '';

    /** @var string */
    protected $providerRefreshToken = '';

    /** @var string */
    protected $providerClientSecret = '';

    /** @var string */
    protected $providerClientId = '';

    /** @var Session */
    protected $session;

    /**
     * ProviderInterface constructor.
     *
     * @param MediaAccount                $mediaAccount
     * @param string                      $providerAccountId
     * @param string                      $providerClientId
     * @param string                      $providerClientSecret
     * @param string                      $providerToken
     * @param string                      $providerRefreshToken
     * @param Session                     $session
     * @param OutputInterface|null        $output
     * @param EntityManager|null          $em
     * @param CampaignSettingsHelper|null $campaignSettingsHelper
     */
    public function __construct(
        $mediaAccount,
        $providerAccountId = '',
        $providerClientId = '',
        $providerClientSecret = '',
        $providerToken = '',
        $providerRefreshToken = '',
        $session,
        $output = null,
        $em = null,
        $campaignSettingsHelper = null
    ) {
        $this->mediaAccount           = $mediaAccount;
        $this->providerAccountId      = $providerAccountId;
        $this->providerClientId       = $providerClientId;
        $this->providerClientSecret   = $providerClientSecret;
        $this->providerToken          = $providerToken;
        $this->session                = $session;
        $this->providerRefreshToken   = $providerRefreshToken;
        $this->output                 = $output;
        $this->em                     = $em;
        $this->campaignSettingsHelper = $campaignSettingsHelper;
    }

    /**
     * Update the tokens of a client on pre-save if acquired by this session.
     *
     * @param              $session
     * @param MediaAccount $mediaAccount
     */
    public static function preSaveMediaAccount($session, MediaAccount $mediaAccount)
    {
        $persist = $session->get('mautic.media.helper.persist', []);
        if ($persist) {
            /** @var MediaAccount $account */
            foreach ($persist as $key => $account) {
                if (
                    $account->getProvider() == $mediaAccount->getProvider()
                    && $account->getAccountId() == $mediaAccount->getAccountId()
                    && $account->getClientId() == $mediaAccount->getClientId()
                    && $account->getClientSecret() == $mediaAccount->getClientSecret()
                ) {
                    if (
                        !empty($account->getToken())
                        && empty($mediaAccount->getToken())
                    ) {
                        $mediaAccount->setToken($account->getToken());
                    }
                    if (
                        !empty($account->getRefreshToken())
                        && empty($mediaAccount->getRefreshToken())
                    ) {
                        $mediaAccount->setRefreshToken($account->getRefreshToken());
                    }
                    break;
                }
            }
        }
    }

    /**
     * Get a unique state to be correlated later.
     *
     * @param string $state
     *
     * @return int
     */
    public static function getMediaAccountIdFromState($state = '')
    {
        $result = 0;
        $parts  = explode('-', $state);
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $result = (int) $parts[1];
        }

        return $result;
    }

    /**
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array
     *
     * @throws \Exception
     */
    public function pullData(\DateTime $dateFrom, \DateTime $dateTo)
    {
        return [];
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get the url required to begin an oAuth2 handshake with the provider.
     * State should be "mautic_media_<Media Account ID>_<Unique>.
     *
     * @param string $redirectUri
     *
     * @return string
     */
    public function getAuthUri($redirectUri)
    {
        return '';
    }

    /**
     * Handle the callback from the provider. Store anything in session that needs to be persisted with the entity.
     *
     * @param $params
     *
     * @return bool
     */
    public function authCallback($params)
    {
        return false;
    }

    /**
     * Save all the stat entities in queue.
     */
    protected function saveQueue()
    {
        if ($this->stats) {
            $this->em()
                ->getRepository('MauticMediaBundle:Stat')
                ->saveEntities($this->stats);

            $this->stats = [];
            $this->em->clear(Stat::class);
        }
    }

    /**
     * @return EntityManager|null
     *
     * @throws \Doctrine\ORM\ORMException
     */
    protected function em()
    {
        if (!$this->em->isOpen()) {
            $this->em = $this->em->create(
                $this->em->getConnection(),
                $this->em->getConfiguration(),
                $this->em->getEventManager()
            );
        }

        return $this->em;
    }

    /**
     * Save all the stat entities in queue.
     */
    protected function saveMediaAccount()
    {
        if ($this->mediaAccount && $this->mediaAccount->getId()) {
            $this->em()
                ->getRepository('MauticMediaBundle:MediaAccount')
                ->saveEntity($this->mediaAccount);

            $this->em->clear(MediaAccount::class);
        }
        $persist   = $this->session->get('mautic.media.helper.persist', []);
        $persist[] = $this->mediaAccount;
        $this->session->set('mautic.media.helper.persist', $persist);
    }

    /**
     * Get a unique state to be correlated later.
     *
     * @return string
     */
    protected function createState()
    {
        return uniqid(
            implode(
                '-',
                [
                    'mautic',
                    $this->mediaAccount->getId(),
                    $this->mediaAccount->getProvider(),
                ]
            ),
            true
        );
    }
}
