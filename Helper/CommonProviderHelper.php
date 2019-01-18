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

    /** @var string */
    protected $providerAccountId;

    /** @var string */
    protected $mediaAccountId;

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
     * @param int                         $mediaAccountId
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
        $mediaAccountId = 0,
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
        $this->mediaAccountId         = $mediaAccountId;
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
     * Save all the stat entities in queue.
     */
    protected function saveQueue()
    {
        if ($this->stats) {
            if (!$this->em->isOpen()) {
                $this->em = $this->em->create(
                    $this->em->getConnection(),
                    $this->em->getConfiguration(),
                    $this->em->getEventManager()
                );
            }
            $this->em->getRepository('MauticMediaBundle:Stat')
                ->saveEntities($this->stats);

            $this->stats = [];
            $this->em->clear(Stat::class);
        }
    }
}
