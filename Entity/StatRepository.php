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
     * @param                $mediaAccountId
     * @param \DateTime|null $dateFrom
     * @param \DateTime|null $dateTo
     *
     * @return array
     */
    // public function getSourcesByMediaAccount($mediaAccountId, \DateTime $dateFrom = null, \DateTime $dateTo = null)
    // {
    //     $q = $this->getEntityManager()->getConnection()->createQueryBuilder();
    //
    //     $q->select('distinct(s.utm_source)')
    //         ->from(MAUTIC_TABLE_PREFIX.'media_account_stats', 's')
    //         ->where(
    //             $q->expr()->eq('s.media_account_id', (int) $mediaAccountId)
    //         );
    //
    //     if ($dateFrom && $dateTo) {
    //         $q->andWhere('s.date_added BETWEEN FROM_UNIXTIME(:dateFrom) AND FROM_UNIXTIME(:dateTo)')
    //             ->setParameter('dateFrom', $dateFrom->getTimestamp(), \PDO::PARAM_INT)
    //             ->setParameter('dateTo', $dateTo->getTimestamp(), \PDO::PARAM_INT);
    //     }
    //
    //     $utmSources = [];
    //     foreach ($q->execute()->fetchAll() as $row) {
    //         $utmSources[] = $row['utm_source'];
    //     }
    //
    //     return $utmSources;
    // }


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
<<<<<<< HEAD
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
                'spend,'.
                'cpc,'.
                'cpm,'.
                'cpp,'.
                'ctr,'.
                'impressions,'.
                'clicks,'.
                'reach'.
                ') VALUES ('.implode(
=======
                'INSERT INTO '.MAUTIC_TABLE_PREFIX.'media_account_stats '.
                '(date_added, campaign_id, provider, media_account_id, provider_campaign_id, provider_campaign_name, provider_account_id, provider_account_name, spend, cpc, cpm) '.
                'VALUES ('.implode(
>>>>>>> origin/master
                    '),(',
                    array_fill(
                        0,
                        count($entities),
<<<<<<< HEAD
                        'FROM_UNIXTIME(?),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?'
=======
                        'FROM_UNIXTIME(?),?,?,?,?,?,?,?,?,?,?'
>>>>>>> origin/master
                    )
                ).') '.
                'ON DUPLICATE KEY UPDATE '.
                'campaign_id = VALUES(campaign_id), '.
                'spend = VALUES(spend), '.
                'cpc = VALUES(cpc), '.
<<<<<<< HEAD
                'cpm = VALUES(cpm), '.
                'cpp = VALUES(cpp), '.
                'ctr = VALUES(ctr), '.
                'impressions = VALUES(impressions), '.
                'clicks = VALUES(clicks), '.
                'reach = VALUES(reach)'
=======
                'cpm = VALUES(cpm)'
>>>>>>> origin/master
            );

        $count = 0;
        foreach ($entities as $entity) {
            /** @var Stat $entity */
            $q->bindValue(++$count, $entity->getDateAdded()->getTimestamp(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getCampaignId(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getProvider(), Type::STRING);
            $q->bindValue(++$count, $entity->getMediaAccountId(), Type::STRING);
<<<<<<< HEAD
            $q->bindValue(++$count, $entity->getProviderAccountId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAccountName(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderCampaignId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderCampaignName(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAdsetId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAdsetName(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAdId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAdName(), Type::STRING);
            $q->bindValue(++$count, $entity->getSpend(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpc(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpm(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpp(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCtr(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getImpressions(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getClicks(), Type::INTEGER);
            $q->bindValue(++$count, $entity->getReach(), Type::INTEGER);
=======
            $q->bindValue(++$count, $entity->getProviderCampaignId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderCampaignName(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAccountId(), Type::STRING);
            $q->bindValue(++$count, $entity->getProviderAccountName(), Type::STRING);
            $q->bindValue(++$count, $entity->getSpend(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpc(), Type::FLOAT);
            $q->bindValue(++$count, $entity->getCpm(), Type::FLOAT);
>>>>>>> origin/master
        }

        $q->execute();
    }
}
