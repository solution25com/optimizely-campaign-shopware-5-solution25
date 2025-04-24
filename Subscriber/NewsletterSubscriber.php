<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */
namespace OptivoBroadmail\Subscriber;

use OptivoBroadmail\Service\NewsletterSubscriberService;
use Psr\Log\LoggerInterface;
use Shopware\Models\Customer\Customer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class manages the subscription for Frontend events, especially
 * for registration and simple subscription.
 */
class NewsletterSubscriber extends BaseSubscriber
{
    /**
     * @var NewsletterSubscriberService
     */
    private $subscriberService;

    /**
     * @inheritdoc
     *
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        NewsletterSubscriberService $subscriberService
    ) {
        $this->subscriberService = $subscriberService;

        parent::__construct($container, $logger);
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend_Register'         => 'onPostDispatchSecureFrontendRegister',
            'Enlight_Controller_Action_PostDispatch_Frontend_Newsletter'       => 'onPostDispatchSecureFrontendNewsletter',
            'Shopware_Modules_Admin_SaveRegister_Successful'                   => 'onSaveRegisterSuccessful',
            'sAdmin::sNewsletterSubscription::after'                           => 'sNewsletterSubscriptionAfter',
            'sAdmin::sUpdateNewsletter::after'                                 => 'sUpdateNewsletterAfter',
        ];
    }

    /**
     * this listener is to extend a template for register
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchSecureFrontendRegister(\Enlight_Event_EventArgs $args)
    {
        $subject = $args->getSubject();
        /** @var \Enlight_View_Default $view */
        $view = $subject->View();
        Shopware()->Snippets()->addConfigDir(
            $this->getPluginDir() . '/Resources/snippets/'
        );
        $view->addTemplateDir($this->getPluginDir() . '/Resources/views/');
        $view->extendsTemplate('frontend/plugins/OptivoBroadmail/templates/index.tpl');
    }

    /**
     * this function checks, if any error occurs while newsletter subscription
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchSecureFrontendNewsletter(\Enlight_Event_EventArgs $args)
    {
        $subject = $args->getSubject();
        /** @var \Enlight_View_Default $view */
        $view = $subject->View();

        $sStatus = array(
            'code'    => $view->sStatus['code'],
            'message' => $view->sStatus['message'],
        );

        if (Shopware()->Front()->Request()->has('newsletterRegisterError')) {
            $view->assign('newsletterRegisterError',
                Shopware()->Front()->Request()->getParam('newsletterRegisterError'));
            $view->assign('sStatus',
                array('code' => -1, 'message' => Shopware()->Front()->Request()->getParam('newsletterRegisterError')));
        }

        if ($result = Shopware()->Front()->Request()->has('newsletterRegister')) {
            $result = Shopware()->Front()->Request()->getParam('newsletterRegister');
            if ($result['result'] == 1) {
                $view->assign('newsletterRegisterDoubleOptinSuccessful', true);
            }
        }

        if (Shopware()->Front()->Request()->has('unsubscribeSuccessful')) {
            $view->assign('unsubscribeSuccessful', Shopware()->Front()->Request()->getParam('unsubscribeSuccessful'));
        }

        $view->assign('sUserLoggedIn', Shopware()->Modules()->Admin()->sCheckUser());
        $view->assign('sStatus', $sStatus);
        Shopware()->Snippets()->addConfigDir(
            $this->getPluginDir() . '/Resources/snippets/'
        );
        $view->addTemplateDir($this->getPluginDir() . '/Resources/views/');
        $view->extendsTemplate('frontend/plugins/OptivoBroadmail/templates/newsletterSubscription.tpl');
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onSaveRegisterSuccessful(\Enlight_Event_EventArgs $args)
    {
        $registerData = Shopware()->Front()->Request()->get('register');
        if (empty($registerData['newsletter']['subscribe'])) {
            return;
        }
        $userId = $args->get('id');
        $user = $this->subscriberService->getUserById($userId);

        if (!is_null($user) && ($user instanceof Customer)) {
            $email = $user->getEmail();
            $additionalData = $registerData['additional'];
            $this->subscriberService->subscribe($user, $email, $additionalData);
        }
    }

    /**
     * This function makes an API call to unsubscribe the given email
     *
     * @param \Enlight_Hook_HookArgs $args
     * @throws \Enlight_Exception
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function sNewsletterSubscriptionAfter(\Enlight_Hook_HookArgs $args)
    {
        $unsubscribe = $args->getUnsubscribe();
        $isUnsubscribeRequest = Shopware()->Front()->Request()->get('subscribeToNewsletter');
        if ($unsubscribe && $isUnsubscribeRequest == '-1') {
            $result = $this->subscriberService->unsubscribe($args->getEmail());
            if ($result['success'] === true && $result['message'] === 'ok') {
                Shopware()->Front()->Request()->setParam('unsubscribeSuccessful', true);
                Shopware()->Front()->Request()->setParam('unsubscribeSuccessfulResult', $result);
            }
        }
    }

    public function sUpdateNewsletterAfter(\Enlight_Hook_HookArgs $args)
    {
        $status = $args->getStatus();
        $email = $args->getEmail();
        $isCustomer = $args->getCustomer();
        if(!$status) {
            $this->subscriberService->unsubscribe($email);
        } else {
            $userId = $args->get('id');
            $user = $this->subscriberService->getUserById($userId);
            $this->subscriberService->subscribe($user, $email);
        }
    }
}
