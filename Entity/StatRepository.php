<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Entity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\MasterSlaveConnection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * Class StatRepository.
 */
class StatRepository extends CommonRepository
{
    /**
     * Fetch the base stat data from the database.
     *
     * @param      $mediaAccountId
     * @param      $type
     * @param null $fromDate
     * @param null $toDate
     *
     * @return array
     */
    public function getStats($mediaAccountId, $type, $fromDate = null, $toDate = null)
    {
        $q = $this->createQueryBuilder('s');

        $expr = $q->expr()->andX(
            $q->expr()->eq('IDENTITY(s.media_account_id)', (int) $mediaAccountId),
            $q->expr()->eq('s.type', ':type')
        );

        if ($fromDate) {
            $expr->add(
                $q->expr()->gte('s.dateAdded', ':fromDate')
            );
            $q->setParameter('fromDate', $fromDate);
        }
        if ($toDate) {
            $expr->add(
                $q->expr()->lte('s.dateAdded', ':toDate')
            );
            $q->setParameter('toDate', $toDate);
        }

        $q->where($expr)
            ->setParameter('type', $type);

        return $q->getQuery()->getArrayResult();
    }

    /**
     * @param $mediaAccountId
     * @param $provider
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getProviderAccounts($mediaAccountId, $provider)
    {
        $alias = 's';
        $query = $this->slaveQueryBuilder();
        $query->select($alias.'.provider_account_id, '.$alias.'.provider_account_name');
        $query->from(MAUTIC_TABLE_PREFIX.$this->getTableName(), $alias);

        // Only provide accounts with recent activity.
        $fromDate = new \DateTime('-30 days');

        // Query structured to use the unique_by_ad unique index.
        $query->add(
            'where',
            $query->expr()->andX(
                $query->expr()->gte($alias.'.date_added', 'FROM_UNIXTIME(:fromDate)'),
                $query->expr()->eq($alias.'.provider', ':provider'),
                $query->expr()->eq($alias.'.media_account_id', (int) $mediaAccountId),
                $query->expr()->isNotNull($alias.'.provider_ad_id')
            )
        );
        $query->setParameter('provider', $provider);
        $query->setParameter('fromDate', $fromDate->getTimestamp());

        $query->groupBy($alias.'.provider_account_id');

        $campaigns = [];
        foreach ($query->execute()->fetchAll() as $campaign) {
            $campaigns[$campaign['provider_account_id']] = $campaign['provider_account_name'];
        }

        return $campaigns;
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
            // Prefer a slave connection if available.
            $connection->connect('slave');
        }

        return new QueryBuilder($connection);
    }

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
                'INSERT INTO '.MAUTIC_TABLE_PREFIX.'media_account_stats ('.
                'date_added,'.
                'campaign_id,'.
                'provider,'.
                'media_account_id,'.
                'provider_account_id,'.
                'provider_account_name,'.
                'provider_campaign_id,'.
                'provider_campaign_name,'.
                'provider_adset_id,'.
                'provider_adset_name,'.
                'provider_ad_id,'.
                'provider_ad_name,'.
                'currency,'.
                'spend,'.
                'cpc,'.
                'cpm,'.
                'cpp,'.
                'ctr,'.
                'impressions,'.
                'clicks,'.
                'reach'.
                ') VALUES ('.implode(
                    '),(',
                    array_fill(
                        0,
                        count($entities),
                        'FROM_UNIXTIME(?),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?'
                    )
                ).') '.
                'ON DUPLICATE KEY UPDATE '.
                'campaign_id = VALUES(campaign_id), '.
                'spend = VALUES(spend), '.
                'cpc = VALUES(cpc), '.
                'cpm = VALUES(cpm), '.
                'cpp = VALUES(cpp), '.
                'ctr = VALUES(ctr), '.
                'impressions = VALUES(impressions), '.
                'clicks = VALUES(clicks), '.
                'reach = VALUES(reach)'
            );

        $count = 0;
        foreach ($entities as $entity) {
            /* @var Stat $entity */
            $q->bindValue(++$count, $entity->getDateAdded()->getTimestamp(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getCampaignId(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getProvider(), Type::STRING);
            $q->bindValue(++$count, $entity->getMediaAccountId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAccountId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAccountName(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderCampaignId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderCampaignName(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAdsetId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAdsetName(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAdId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAdName(), Type::STRING);
            $q->bindValue(++$count, $entity->getCurrency(), Type::STRING);
            $q->bindValue(++$count, $entity->getSpend(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpc(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpm(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpp(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCtr(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getImpressions(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getClicks(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getReach(), Type::INTEGER);
        }

        $q->execute();
    }

    /**
     * @param int        $campaignId
     * @param \DateTime  $from
     * @param \DateTime  $to
     * @param null/array $args
     *
     * @return array
     */
    public function getCampaignSpend($campaignId, $from, $to, $args = null)
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->from(MAUTIC_TABLE_PREFIX.'media_account_stats', 's')
            ->select('DATE_FORMAT(s.date_added, "'.$args['dbunit'].'") AS spendDate, s.campaign_id, SUM(s.spend) AS spend');

        $expr = $q->expr()->andX(
            $q->expr()->eq('s.campaign_id', (int) $campaignId),
            $q->expr()->gte('s.date_added', 'FROM_UNIXTIME(:fromDate)'),
            $q->expr()->lte('s.date_added', 'FROM_UNIXTIME(:toDate)')
        );
        $q->setParameter('fromDate', (int) $from->getTimestamp());
        $q->setParameter('toDate', (int) $to->getTimestamp());

        $q->where($expr);
        $q->addGroupBy('DATE_FORMAT(s.date_added, "'.$args['dbunit'].'")');

        return $q->execute()->fetchAll();
    }
}
