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

    /** @var array */
    private $guesses = [];

    /**
     * CampaignMapHelper constructor.
     */
    public function __construct()
    {
        $this->utf8Helper = new UTF8Helper();
    }

    /**
     * @param array $campaignNames
     * @param bool  $force
     */
    public function setCampaignNames($campaignNames = [], $force = false)
    {
        if (!$this->campaignNames || $force) {
            foreach ($campaignNames as &$campaignName) {
                $this->simplify($campaignName);
            }
            $this->campaignNames = $campaignNames;
            if ($campaignNames) {
                $this->campaignNamesFlipped = array_flip($campaignNames);
            }
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
        $replacements = [
            '-'  => ' ',
            '_'  => ' ',
            '!'  => ' ',
            '/'  => ' ',
            '\\' => ' ',
            '.'  => ' ',
            '  ' => ' ',
        ];
        $string       = trim(
            str_replace(
                array_keys($replacements),
                array_values($replacements),
                strtolower($this->utf8Helper::fixUTF8($string))
            )
        );

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
        $origName          = (string) $providerCampaignName;
        if (!isset($this->guesses[$origName])) {
            $shortest = -1;
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
                        } elseif ($lev <= $shortest || -1 == $shortest) {
                            $closestCampaignId = $campaignId;
                            $shortest          = $lev;
                        }
                    }
                }
            }
            $this->guesses[$origName] = $closestCampaignId;
        } else {
            $closestCampaignId = $this->guesses[$origName];
        }

        return $closestCampaignId;
    }
}
