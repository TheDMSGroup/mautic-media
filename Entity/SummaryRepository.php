<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Entity;

use Doctrine\DBAL\Types\Type;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * Class SummaryRepository.
 */
class SummaryRepository extends CommonRepository
{
    /**
     * Insert or update batches.
     *
     * @param array $entities
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function saveEntities($entities)
    {
        $q = $this->getEntityManager()
            ->getConnection()
            ->prepare(
                'INSERT INTO '.MAUTIC_TABLE_PREFIX.'media_account_summary ('.
                'date_added,'.
                'date_modified,'.
                'provider,'.
                'media_account_id,'.
                'provider_account_id,'.
                'provider_account_name,'.
                'currency,'.
                'spend,'.
                'cpc,'.
                'cpm,'.
                'ctr,'.
                'impressions,'.
                'clicks,'.
                'complete,'.
                'final'.
                ') VALUES ('.implode(
                    '),(',
                    array_fill(
                        0,
                        count($entities),
                        'FROM_UNIXTIME(?),FROM_UNIXTIME(?),?,?,?,?,?,?,?,?,?,?,?,?,?'
                    )
                ).') '.
                'ON DUPLICATE KEY UPDATE '.
                'date_modified = VALUES(date_modified), '.
                'provider_account_name = VALUES(provider_account_name), '.
                'spend = VALUES(spend), '.
                'cpc = VALUES(cpc), '.
                'cpm = VALUES(cpm), '.
                'ctr = VALUES(ctr), '.
                'impressions = VALUES(impressions), '.
                'clicks = VALUES(clicks), '.
                'complete = VALUES(complete), '.
                'final = VALUES(final)'
            );

        $count = 0;
        krsort($entities);
        foreach ($entities as $entity) {
            /* @var Summary $entity */
            $q->bindValue(++$count, $entity->getDateAdded()->getTimestamp(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getDateModified()->getTimestamp(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getProvider(), Type::STRING);
            $q->bindValue(++$count, $entity->getMediaAccountId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAccountId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAccountName(), Type::STRING);
            $q->bindValue(++$count, $entity->getCurrency(), Type::STRING);
            $q->bindValue(++$count, $entity->getSpend(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpc(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpm(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCtr(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getImpressions(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getClicks(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getComplete(), Type::BOOLEAN);
            $q->bindValue(++$count, $entity->getFinal(), Type::BOOLEAN);
        }

        $q->execute();
    }
}
