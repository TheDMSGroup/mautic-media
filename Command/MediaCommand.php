<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use MauticPlugin\MauticMediaBundle\Helper\SettingsHelper;
use MauticPlugin\MauticMediaBundle\Model\MediaModel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI Command : Warms the media caches for all users.
 *
 * php app/console mautic:media:boost
 */
class MediaCommand extends ModeratedCommand
{
    /**
     * Maintenance command line task.
     */
    protected function configure()
    {
        $this->setName('mautic:media:pull')
            ->setDescription('Pull media spend statistics.')
            ->addOption(
                '--limit',
                '-l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of accounts to pull.',
                50
            );
        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        if (!$this->checkRunStatus($input, $output)) {
            return 0;
        }
        $limit = $input->getOption('limit');

        /** @var SettingsHelper $settingsHelper */
        $settingsHelper = $container->get('mautic.media.helper.settings');
        $sharedCache    = (bool) $settingsHelper->getShareCaches();

        /** @var MediaModel $model */
        $model = $container->get('mautic.media.model.warm');
        $model->warm($limit, $sharedCache);

        $this->completeRun();

        return 0;
    }
}
