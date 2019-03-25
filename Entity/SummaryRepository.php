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

use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\Query\QueryBuilder;
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
                'final,'.
                'final_date,'.
                'provider_date,'.
                'pull_count'.
                ') VALUES ('.implode(
                    '),(',
                    array_fill(
                        0,
                        count($entities),
                        'FROM_UNIXTIME(?),FROM_UNIXTIME(?),?,?,?,?,?,?,?,?,?,?,?,?,?,FROM_UNIXTIME(?),?,?'
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
                'final = VALUES(final), '.
                'final_date = VALUES(final_date), '.
                'provider_date = VALUES(provider_date), '.
                'pull_count = pull_count + VALUES(pull_count)'
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
            $q->bindValue(++$count, $entity->getFinalDate()->getTimestamp(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getProviderDate(), Type::STRING);
            $q->bindValue(++$count, $entity->getPullCount(), Type::INTEGER);
        }

        $q->execute();
    }

    /**
     * @param int           $mediaAccountId
     * @param string        $provider
     * @param \DateTimeZone $timezone
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getDatesNeedingFinalization($mediaAccountId, $provider, \DateTimeZone $timezone)
    {
        $dates = [];
        $alias = 's';
        $query = $this->slaveQueryBuilder();
        $query->select(
            $alias.'.provider_date'
        );
        $query->from(MAUTIC_TABLE_PREFIX.$this->getTableName(), $alias);

        // Query structured to use the campaign_mapping index.
        $query->add(
            'where',
            $query->expr()->andX(
                $query->expr()->lte($alias.'.final_date', 'NOW()'),
                $query->expr()->eq($alias.'.provider', ':provider'),
                $query->expr()->eq($alias.'.media_account_id', (int) $mediaAccountId),
                $query->expr()->eq($alias.'.final', 0)
            )
        );
        $query->setParameter('provider', $provider);
        $query->groupBy($alias.'.final_date, '.$alias.'.provider_date');
        // Start with the newest and go backward. We always care more about recent data accuracy.
        $query->orderBy($alias.'.final_date', 'DESC');
        $query->setMaxResults(90);

        foreach ($query->execute()->fetchAll() as $row) {
            // Recall these as local timezones for the pullData method.
            $dates[] = $row['provider_date'];
        }

        return $dates;
    }

    /**
     * Create a DBAL QueryBuilder preferring a slave connection if available.
     *
     * @return QueryBuilder
     */
    private function slaveQueryBuilder()
    {
        /** @var Connection $connection */
        $connection = $this->getEntityManager()->getConnection();
        if ($connection instanceof MasterSlaveConnection) {
            $connection->connect('slave');
        }

        return new QueryBuilder($connection);
    }
}
