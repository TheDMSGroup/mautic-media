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
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Model\MediaAccountModel;
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
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of accounts to pull.',
                0
            )
            ->addOption(
                'date-from',
                '',
                InputOption::VALUE_OPTIONAL,
                'Oldest date to pull spend data for. Leave blank to pull the last hour or day as needed.',
                '-1 hour'
            )
            ->addOption(
                'date-to',
                '',
                InputOption::VALUE_OPTIONAL,
                'Newest date to pull spend data for. Leave blank for the current date.',
                'now'
            )
            ->addOption(
                'provider',
                '',
                InputOption::VALUE_OPTIONAL,
                'Optionally specify google, facebook, snapchat, bing, etc.',
                ''
            )
            ->addOption(
                'media-account',
                'i',
                InputOption::VALUE_OPTIONAL,
                'The ID of the media account you wish to update.',
                null
            );

        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        if (!$this->checkRunStatus($input, $output)) {
            return 0;
        }
        $limit          = $input->getOption('limit');
        $mediaAccountId = $input->getOption('media-account');
        $dateFromString = $input->getOption('date-from');
        $dateToString   = $input->getOption('date-to');
        $provider       = strtolower($input->getOption('provider'));

        /** @var MediaAccountModel $model */
        $model = $container->get('mautic.media.model.media');
        $repo  = $model->getRepository();

        $filters = [
            'filter' => [
                'force' => [
                    [
                        'column' => 'm.isPublished',
                        'expr'   => 'eq',
                        'value'  => true,
                    ],
                ],
            ],
        ];
        if ($limit) {
            $filters['limit'] = $limit;
        }
        if ($mediaAccountId) {
            $filters['filter']['force'][] = [
                'column' => 'm.id',
                'expr'   => 'eq',
                'value'  => (int) $mediaAccountId,
            ];
        }
        if ($provider) {
            $filters['filter']['force'][] = [
                'column' => 'm.provider',
                'expr'   => 'eq',
                'value'  => $provider,
            ];
        }
        $mediaAccounts = $repo->getEntities($filters);
        foreach ($mediaAccounts as $id => $mediaAccount) {
            /** @var $mediaAccount MediaAccount */
            if (!$mediaAccount->getIsPublished()) {
                $output->writeln(
                    '<error>The Media Account '.$mediaAccount->getName(
                    ).' is unpublished. Please publish it to pull data.</error>'
                );
                continue;
            }
            $output->writeln('<info>Pulling data for Media Account '.$mediaAccount->getName().'</info>');
            $model->pullData($mediaAccount, $dateFromString, $dateToString, $output);
        }

        $this->completeRun();

        return 0;
    }
}
