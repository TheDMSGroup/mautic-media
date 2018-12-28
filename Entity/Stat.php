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

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * Class Stat.
 */
class Stat
{
    /** @var int $id */
    private $id;

    /** @var int mediaAccountId */
    private $mediaAccountId = 0;

    /** @var \DateTime $dateAdded */
    private $dateAdded;

    /** @var float $spend */
    private $spend;

    /** @var float $cpm */
    private $cpm;

    /** @var float $cpc */
    private $cpc;

    /** @var int $campaignId */
    private $campaignId = 0;

    /** @var int $eventId */
    private $eventId = 0;

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('media_account_stats')
            ->setCustomRepositoryClass('MauticPlugin\MauticMediaBundle\Entity\StatRepository');

        $builder->addId();

        $builder->addNamedField('mediaAccountId', 'integer', 'media_account_id', true);

        $builder->addDateAdded();

        $builder->createField('spend', 'decimal')
            ->precision(19)
            ->scale(4)
            ->nullable()
            ->build();

        $builder->createField('cpc', 'decimal')
            ->precision(19)
            ->scale(4)
            ->nullable()
            ->build();

        $builder->createField('cpm', 'decimal')
            ->precision(19)
            ->scale(4)
            ->nullable()
            ->build();

        // $builder->addNamedField('utmSource', 'string', 'utm_source', true);
        $builder->addNamedField('campaignId', 'integer', 'campaign_id', false);
        $builder->addNamedField('eventId', 'integer', 'event_id', false);

        // $builder->addIndex(
        //     ['contactclient_id', 'type', 'date_added'],
        //     'contactclient_type_date_added'
        // );

        // $builder->addIndex(
        //     ['contactclient_id', 'type', 'utm_source', 'date_added'],
        //     'contactclient_type_utm_source_date_added'
        // );

        // $builder->addIndex(
        //     ['contactclient_id', 'utm_source'],
        //     'contactclient_utm_source'
        // );
        // $builder->addIndex(
        //     ['contact_id'],
        //     'contact_id'
        // );

        // $builder->addIndex(
        //     ['contact_id', 'contactclient_id'],
        //     'contact_id_contactclient_id'
        // );

        // $builder->addIndex(
        //     ['campaign_id', 'date_added'],
        //     'campaign_id_date_added'
        // );
    }

    /**
     * @return array
     */
    public static function getAllTypes()
    {
        $result = [];
        try {
            $reflection = new \ReflectionClass(__CLASS__);
            $result     = $reflection->getConstants();
        } catch (\ReflectionException $e) {
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return float
     */
    public function getCpm()
    {
        return $this->cpm;
    }

    /**
     * @param float $spend
     *
     * @return $this
     */
    public function setCpm($cpm)
    {
        $this->cpm = $cpm;

        return $this;
    }

    /**
     * @return float
     */
    public function getCpc()
    {
        return $this->cpc;
    }

    /**
     * @param float $cpc
     *
     * @return $this
     */
    public function setCpc($cpc)
    {
        $this->cpc = $cpc;

        return $this;
    }


    /**
     * @return float
     */
    public function getSpend()
    {
        return $this->spend;
    }

    /**
     * @param float $spend
     *
     * @return $this
     */
    public function setSpend($spend)
    {
        $this->spend = $spend;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateAdded()
    {
        return $this->dateAdded;
    }

    /**
     * @param mixed $dateAdded
     *
     * @return Stat
     */
    public function setDateAdded($dateAdded)
    {
        $this->dateAdded = $dateAdded;

        return $this;
    }

    /**
     * @return int
     */
    public function getMediaAccountId()
    {
        return $this->mediaAccountId;
    }

    /**
     * @param int $mediaAccountId
     *
     * @return $this
     */
    public function setMediaAccountId($mediaAccountId)
    {
        $this->mediaAccountId = $mediaAccountId;

        return $this;
    }

    /**
     * @return int
     */
    public function getCampaignId()
    {
        return $this->campaignId;
    }

    /**
     * @param int $campaignId
     *
     * @return Stat
     */
    public function setCampaignId($campaignId)
    {
        $this->campaignId = $campaignId;

        return $this;
    }

    /**
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @param int $eventId
     *
     * @return Stat
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;

        return $this;
    }
}
