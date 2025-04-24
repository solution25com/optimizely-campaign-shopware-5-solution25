<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Subscriber;

/**
 * Backend Subscriber
 *
 * Configure here the Event Subscriptions for any Backend scope
 * e.g. adding additional smarty views or assigning a variable.
 *
 * @package OptivoBroadmail\Subscriber
 */
class BackendSubscriber extends BaseSubscriber
{
    /**
     * Helper method to define event subscriptions
     *
     * @return array Structure with event subscriptions "<event> => array(<listener>, ..., <listener_n>)"
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Mail' => 'onBackendMailPostDispatch',
        ];
    }

    /**
     * Extend email template administration
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onBackendMailPostDispatch(\Enlight_Event_EventArgs $args)
    {
        $ctrl = $args->getSubject();
        $view = $ctrl->View();
        $req = $args->getRequest();
        $actionName = $req->getActionName();

        $view->addTemplateDir($this->getPluginDir() . '/Resources/views/');

        if ($actionName === 'load') {
            $view->extendsTemplate('backend/mail/view/main/custom/editor.js');
            $view->extendsTemplate('backend/mail/view/main/custom/form.js');
            $view->extendsTemplate('backend/mail/controller/custom/main.js');
        }
    }
}
