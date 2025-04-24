<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Subscriber;

/**
 * Frontend Subscriber
 *
 * Configure here the Event Subscriptions for any Backend scope
 * e.g. adding additional smarty views or assigning a variable.
 *
 * @package OptivoBroadmail\Subscriber
 */
class FrontendSubscriber extends BaseSubscriber
{
    /**
     * Helper method to define event subscriptions
     *
     * @return array Structure with event subscriptions "<event> => array(<listener>, ..., <listener_n>)"
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_OptivoBroadmail' => 'registerController',
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @return string
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function registerController(\Enlight_Event_EventArgs $args)
    {
        $this->getContainer()->get('Template')->addTemplateDir($this->getPluginDir() . '/Resources/views/');

        Shopware()->Snippets()->addConfigDir(
            $this->getPluginDir() . '/Resources/snippets/'
        );

        return $this->getPluginDir() . '/Controllers/Frontend/OptivoBroadmail.php';
    }
}
