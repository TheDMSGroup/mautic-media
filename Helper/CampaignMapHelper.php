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

use Mautic\CoreBundle\Helper\UTF8Helper;

/**
 * Class CampaignMapHelper.
 */
class CampaignMapHelper
{
    /** @var array */
    private $campaignNames = [];

    /** @var array */
    private $campaignNamesFlipped = [];

    /** @var UTF8Helper */
    private $utf8Helper;

    /**
     * CampaignMapHelper constructor.
     */
    public function __construct()
    {
        $this->utf8Helper = new UTF8Helper();
    }

    /**
     * @param array $campaignNames
     */
    public function setCampaignNames($campaignNames = [])
    {
        foreach ($campaignNames as &$campaignName) {
            $this->simplify($campaignName);
        }
        $this->campaignNames        = $campaignNames;
        if ($campaignNames) {
            $this->campaignNamesFlipped = array_flip($campaignNames);
        }
    }

    /**
     * Simplify a string for levenshtein distance checking.
     *
     * @param string $string
     *
     * @return string
     */
    private function simplify(&$string = '')
    {
        $string = trim(strtolower($this->utf8Helper::fixUTF8($string)));

        return $string;
    }

    /**
     * @param string $providerCampaignName
     * @param int    $maxDistance
     *
     * @return int|string
     */
    public function guess($providerCampaignName = '', $maxDistance = 3)
    {
        $closestCampaignId = 0;
        $shortest          = -1;
        $this->simplify($providerCampaignName);
        if (strlen($providerCampaignName)) {
            // Exact match check first as it's drastically faster.
            if (isset($this->campaignNamesFlipped[$providerCampaignName])) {
                $closestCampaignId = $this->campaignNamesFlipped[$providerCampaignName];
            } else {
                // Levenshtein distance check second.
                foreach ($this->campaignNames as $campaignId => $campaignName) {
                    $lev = levenshtein($providerCampaignName, $campaignName);
                    if (0 === $lev) {
                        $closestCampaignId = $campaignId;
                        break;
                    } elseif ($lev > $maxDistance) {
                        continue;
                    } elseif ($lev <= $shortest || $shortest == -1) {
                        $closestCampaignId = $campaignId;
                        $shortest          = $lev;
                    }
                }
            }
        }

        return $closestCampaignId;
    }
}
