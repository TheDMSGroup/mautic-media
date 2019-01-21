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
use MauticPlugin\MauticMediaBundle\Entity\MediaAccount;
use MauticPlugin\MauticMediaBundle\Helper\CommonProviderHelper;
use MauticPlugin\MauticMediaBundle\Model\MediaAccountModel;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

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
        $params = [
            'code'  => (string) InputHelper::clean($request->get('code')),
            'state' => (string) InputHelper::clean($request->get('state')),
        ];

        /** @var MediaAccountModel $model */
        $model        = $this->get('mautic.media.model.media');
        $mediaAccount = new MediaAccount();
        $mediaAccount->setProvider($provider);

        /** @var CommonProviderHelper $providerHelper */
        $providerHelper = $model->getProviderHelper($mediaAccount);

        $result = $providerHelper->authCallback($params);

        if ($result) {
            $message = $this->translator->trans('mautic.media.auth.success');
        } else {
            $message = $this->translator->trans('mautic.media.auth.fail');
        }

        return $this->render(
            'MauticMediaBundle:Auth:postauth.html.php',
            ['result' => $result, 'message' => $message, 'alert' => '', 'data' => '']
        );
    }

    /**
     * @param $integration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function authStatusAction($integration)
    {
        // @todo
        $tmp = 3;
        // $postAuthTemplate = 'MauticPluginBundle:Auth:postauth.html.php';
        //
        // $session     = $this->get('session');
        // $postMessage = $session->get('mautic.integration.postauth.message');
        // $userData    = [];
        //
        // if (isset($integration)) {
        //     $userData = $session->get('mautic.integration.'.$integration.'.userdata');
        // }
        //
        // $message = $type = '';
        // $alert   = 'success';
        // if (!empty($postMessage)) {
        //     $message = $this->translator->trans($postMessage[0], $postMessage[1], 'flashes');
        //     $session->remove('mautic.integration.postauth.message');
        //     $type = $postMessage[2];
        //     if ($type == 'error') {
        //         $alert = 'danger';
        //     }
        // }
        //
        // return $this->render($postAuthTemplate, ['message' => $message, 'alert' => $alert, 'data' => $userData]);
    }

    /**
     * @param $integration
     *
     * @return RedirectResponse
     */
    public function authUserAction($integration)
    {
        // @todo
        $tmp = 1;
        // /** @var \Mautic\PluginBundle\Helper\IntegrationHelper $integrationHelper */
        // $integrationHelper = $this->factory->getHelper('integration');
        // $integrationObject = $integrationHelper->getIntegrationObject($integration);
        //
        // $settings['method']      = 'GET';
        // $settings['integration'] = $integrationObject->getName();
        //
        // /** @var \Mautic\PluginBundle\Integration\AbstractIntegration $integrationObject */
        // $event = $this->dispatcher->dispatch(
        //     PluginEvents::PLUGIN_ON_INTEGRATION_AUTH_REDIRECT,
        //     new PluginIntegrationAuthRedirectEvent(
        //         $integrationObject,
        //         $integrationObject->getAuthLoginUrl()
        //     )
        // );
        // $oauthUrl = $event->getAuthUrl();
        //
        // $response = new RedirectResponse($oauthUrl);
        //
        // return $response;
    }
}
