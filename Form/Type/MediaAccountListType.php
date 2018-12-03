<?php

namespace MauticPlugin\MauticMediaBundle\Form\Type;

use MauticPlugin\MauticMediaBundle\Model\MediaAccountModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class MediaAccountListType.
 */
class MediaAccountListType extends AbstractType
{
    /**
     * @var MediaAccountModel
     */
    protected $mediaModel;

    private $repo;

    /**
     * @param MediaAccountModel $mediaModel
     */
    public function __construct(MediaAccountModel $mediaModel)
    {
        $this->mediaModel = $mediaModel;
        $this->repo       = $this->mediaModel->getRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'choices'        => function (Options $options) {
                    $choices = [];

                    $list = $this->repo->getMediaAccountList($options['data']);
                    foreach ($list as $row) {
                        $choices[$row['id']] = $row['name'];
                    }

                    //sort by language
                    ksort($choices);

                    return $choices;
                },
                'expanded'       => false,
                'multiple'       => true,
                'required'       => false,
                'empty_value'    => function (Options $options) {
                    return (empty($options['choices'])) ? 'mautic.media.no.mediaitem.note' : 'mautic.core.form.chooseone';
                },
                'disabled'       => function (Options $options) {
                    return empty($options['choices']);
                },
                'top_level'      => 'variant',
                'variant_parent' => null,
                'ignore_ids'     => [],
            ]
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'media_list';
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return 'choice';
    }
}
