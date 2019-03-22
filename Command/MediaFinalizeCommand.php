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
 * Finalize media spend statistics as needed.
 *
 * php app/console mautic:media:pull
 */
class MediaFinalizeCommand extends ModeratedCommand
{
    /**
     * Maintenance command line task.
     */
    protected function configure()
    {
        $this->setName('mautic:media:finalize')
            ->setAliases(['mautic:media:final'])
            ->setDescription('Finalize media spend statistics as needed.')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of accounts to finalize.',
                0
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
                    ).' is unpublished. Please publish it to finalize data.</error>'
                );
                continue;
            }
            $output->writeln('<info>Finalizing data for Media Account '.$mediaAccount->getName().'</info>');
            $model->finalizeData($mediaAccount, $output);
        }

        $this->completeRun();

        return 0;
    }
}
