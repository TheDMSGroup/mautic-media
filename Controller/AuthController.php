<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticMediaBundle\Controller;

use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\FormBundle\Controller\FormController;
use MauticPlugin\MauticMediaBundle\Helper\CommonProviderHelper;
use MauticPlugin\MauticMediaBundle\Model\MediaAccountModel;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AuthController.
 *
 * Handle requests for authentication against providers specifically for the Media plugin.
 */
class AuthController extends FormController
{
    use MediaAccountDetailsTrait;

    public function __construct()
    {
        $this->setStandardParameters(
            'media',
            'plugin:media:items',
            'media',
            'media',
            '',
            'MauticMediaBundle:MediaAccount',
            null,
            'media'
        );
    }

    /**
     * @param Request $request
     * @param string  $provider
     *
     * @return mixed
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function authCallbackAction(Request $request, $provider)
    {
        $code     = (string) InputHelper::clean($request->get('code'));
        $state    = (string) InputHelper::clean($request->get('state'));
        $result   = false;
        $response = new Response();
        $params   = [
            'code'  => $code,
            'state' => $state,
            'uri'   => $request->getRequestUri(),
        ];

        /** @var MediaAccountModel $model */
        $model          = $this->get('mautic.media.model.media');
        $mediaAccountId = CommonProviderHelper::getMediaAccountIdFromState($state);

        if ($mediaAccountId) {
            $mediaAccount = $model->getEntity($mediaAccountId);
        } else {
            // Assume this is a new mediaAccount in creation.
            $mediaAccount = $this->request->getSession()->get('mautic.media.auth.'.$provider.'.start');
        }

        if ($mediaAccount) {
            /** @var CommonProviderHelper $providerHelper */
            $providerHelper = $model->getProviderHelper($mediaAccount);
            if ($providerHelper) {
                $result = $providerHelper->authCallback($params);
            }
        }

        if ($result) {
            $message = $this->translator->trans('mautic.media.auth.success');
            $alert   = 'success';
            $response->headers->setCookie(
                new Cookie('mauticMediaAuthChange', time(), '+1 minute', '/', null, false, false)
            );
        } else {
            $message = $this->translator->trans('mautic.media.auth.fail');
            $alert   = 'error';
        }

        return $this->render(
            'MauticMediaBundle:Auth:postauth.html.php',
            ['result' => $result, 'message' => $message, 'alert' => $alert, 'data' => ''],
            $response
        );
    }
}
