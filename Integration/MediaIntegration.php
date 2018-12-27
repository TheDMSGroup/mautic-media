<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use MauticPlugin\MauticMediaBundle\Helper\SettingsHelper;

/**
 * Class MediaIntegration.
 */
class MediaIntegration extends AbstractIntegration
{
    /**
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'none';
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Media';
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return 'Media';
    }

    /**
     * @return array
     */
    public function getSupportedFeatures()
    {
        return [];
    }
}
