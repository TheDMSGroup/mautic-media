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
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class MediaAccount.
 */
class MediaAccount extends FormEntity
{
    /** @var string */
    const PROVIDER_BING = 'bing';

    /** @var string */
    const PROVIDER_FACEBOOK = 'facebook';

    /** @var string */
    const PROVIDER_GOOGLE = 'google';

    /** @var string */
    const PROVIDER_SNAPCHAT = 'snapchat';

    /** @var int */
    private $id;

    /** @var string */
    private $description;

    /** @var string */
    private $name;

    /** @var */
    private $category;

    /** @var string */
    private $provider;

    /** @var string Typically an OAUTH2 Client ID */
    private $clientId;

    /** @var string Typically an OAUTH2 Client Secret */
    private $clientSecret;

    /** @var string Typically an OAUTH2 Refresh Token */
    private $refreshToken;

    /** @var \DateTime */
    private $publishUp;

    /** @var \DateTime */
    private $publishDown;

    /** @var string */
    private $campaignMap;

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'name',
            new NotBlank(
                ['message' => 'mautic.core.name.required']
            )
        );

        $metadata->addPropertyConstraint(
            'provider',
            new NotBlank(
                ['message' => 'mautic.media.provider.required']
            )
        );
    }

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('media_account')
            ->setCustomRepositoryClass('MauticPlugin\MauticAccountBundle\Entity\MediaAccountRepository');

        $builder->addIdColumns();

        $builder->addCategory();

        $builder->addPublishDates();

        $builder->addNamedField('provider', 'string', 'provider', false);

        $builder->addNamedField('clientId', 'string', 'client_id', true);

        $builder->addNamedField('clientSecret', 'string', 'client_secret', true);

        $builder->addNamedField('refreshToken', 'string', 'refresh_token', true);

        $builder->addNamedField('campaignMap', 'string', 'campaign_map', true);

    }

    /**
     * Prepares the metadata for API usage.
     *
     * @param $metadata
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata)
    {
        $metadata->setGroupPrefix('Account')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'category',
                ]
            )
            ->addProperties(
                [
                    'description',
                    'clientId',
                    'clientSecret',
                    'refreshToken',
                    'publishUp',
                    'publishDown',
                    'campaignList',
                ]
            )
            ->setGroupPrefix('AccountBasic')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'category',
                    'description',
                ]
            )
            ->build();
    }

    /**
     * Allow these entities to be cloned like core entities.
     */
    public function __clone()
    {
        $this->id = null;

        parent::__clone();
    }

    /**
     * @return string
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * @param string $clientSecret
     *
     * @return $this
     */
    public function setClientSecret($clientSecret)
    {
        $this->isChanged('clientSecret', $clientSecret);

        $this->clientSecret = $clientSecret;

        return $this;
    }

    /**
     * @return string
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @param string $refreshToken
     *
     * @return $this
     */
    public function setRefreshToken($refreshToken)
    {
        $this->isChanged('refreshToken', $refreshToken);

        $this->refreshToken = $refreshToken;

        return $this;
    }


    /**
     * @return string
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     *
     * @return $this
     */
    public function setClientId($clientId)
    {
        $this->isChanged('clientId', $clientId);

        $this->clientId = $clientId;

        return $this;
    }

    /**
     * @return string
     */
    public function getCampaignMap()
    {
        return $this->campaignMap;
    }

    /**
     * @param string $campaignMap
     *
     * @return $this
     */
    public function setCampaignMap($campaignMap)
    {
        $this->isChanged('campaignMap', $campaignMap);

        $this->campaignMap = $campaignMap;

        return $this;
    }

    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @param string $provider
     *
     * @return $this
     */
    public function setProvider($provider)
    {
        $this->isChanged('provider', $provider);

        $this->provider = $provider;

        return $this;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return Account
     */
    public function setDescription($description)
    {
        $this->isChanged('description', $description);

        $this->description = $description;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return Account
     */
    public function setName($name)
    {
        $this->isChanged('name', $name);

        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param mixed $category
     *
     * @return Account
     */
    public function setCategory($category)
    {
        $this->isChanged('category', $category);

        $this->category = $category;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPublishUp()
    {
        return $this->publishUp;
    }

    /**
     * @param mixed $publishUp
     *
     * @return Account
     */
    public function setPublishUp($publishUp)
    {
        $this->isChanged('publishUp', $publishUp);

        $this->publishUp = $publishUp;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPublishDown()
    {
        return $this->publishDown;
    }

    /**
     * @param mixed $publishDown
     *
     * @return Account
     */
    public function setPublishDown($publishDown)
    {
        $this->isChanged('publishDown', $publishDown);

        $this->publishDown = $publishDown;

        return $this;
    }

    /**
     * @return int
     */
    public function getPermissionUser()
    {
        return $this->getCreatedBy();
    }
}
