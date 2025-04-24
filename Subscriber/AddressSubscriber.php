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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class changes the behaviour if a newsletter address (email) changes.
 * This class limits changes to backend scope, except deletion of a
 * newsletter recipient. Here the scope doesn't matter.
 *
 * @package OptivoBroadmail\Subscriber
 */
class AddressSubscriber extends BaseSubscriber
{
    private $oldNewsletterManagerEmail;

    /**
     * @var NewsletterSubscriberService
     */
    private $subscriberService;

    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        NewsletterSubscriberService $subscriberService
    ) {
        parent::__construct($container, $logger);

        $this->subscriberService = $subscriberService;
    }

    /**
     * Helper method to define event subscriptions
     *
     * @return array Structure with event subscriptions "<event> => array(<listener>, ..., <listener_n>)"
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware\Models\Newsletter\Address::postPersist' => 'onNewsletterAddressPostPersist',
            'Shopware\Models\Newsletter\Address::preUpdate'   => 'onNewsletterAddressPreUpdate',
            'Shopware\Models\Newsletter\Address::postUpdate'  => 'onNewsletterAddressPostUpdate',
            'Shopware\Models\Newsletter\Address::postRemove'  => 'onNewsletterAddressPostRemove',
        ];
    }

    /**
     * new newsletter recipient was created in backend -> newsletter manager
     *
     * this is triggerd after a user clicks on the opt in link
     *
     * @param \Enlight_Event_EventArgs $arguments
     */
    public function onNewsletterAddressPostPersist(\Enlight_Event_EventArgs $arguments)
    {
        $request = Shopware()->Front()->Request();
        if (!$this->isBackendChange($request)) {
            return;
        }
        /** @var \Shopware\Models\Newsletter\Address $address */
        $address = $arguments->get('entity');
        if (!empty($address) && ($address instanceof \Shopware\Models\Newsletter\Address)) {
            $email = $address->getEmail();
            $this->subscriberService->subscribe(null, $email);
        }
    }


    /**
     * newsletter recipient (email) was changed in backend -> newsletter manager
     * store his old email to unsubscribe it
     *
     * @param \Enlight_Event_EventArgs $arguments
     */
    public function onNewsletterAddressPreUpdate(\Enlight_Event_EventArgs $arguments)
    {
        /** @var \Shopware\Models\Newsletter\Address $address */
        $address = $arguments->get('entity');
        $this->oldNewsletterManagerEmail = $this->getOldRecipientEmail($address->getId());
    }

    /**
     * newsletter recipient (email) was changed in backend -> newsletter manager
     *
     * @param \Enlight_Event_EventArgs $arguments
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function onNewsletterAddressPostUpdate(\Enlight_Event_EventArgs $arguments)
    {
        $request = Shopware()->Front()->Request();
        if (!$this->isBackendChange($request)) {
            return;
        }
        /** @var \Shopware\Models\Newsletter\Address $address */
        $address = $arguments->get('entity');
        $newEmail = $address->getEmail();
        $oldEmail = $this->oldNewsletterManagerEmail;
        if (!empty($oldEmail) && $oldEmail != $newEmail) {
            $this->subscriberService->resubscribe($oldEmail, $newEmail, null);
        }
    }

    /**
     * newsletter recipient was deleted, not limited to any scope.
     *
     * @param \Enlight_Event_EventArgs $arguments
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function onNewsletterAddressPostRemove(\Enlight_Event_EventArgs $arguments)
    {
        /** @var \Shopware\Models\Newsletter\Address $address */
        $address = $arguments->get('entity');
        if (!empty($address) && ($address instanceof \Shopware\Models\Newsletter\Address)) {
            $email = $address->getEmail();
            $this->subscriberService->unsubscribe($email);
        }
    }

    /**
     * get old recipient email (recipient was changed in backend). plain sql, because doctrine has already new email
     *
     * @param int $addressId
     * @return string
     */
    private function getOldRecipientEmail($addressId)
    {
        $email = Shopware()->Db()->fetchOne("SELECT `email` FROM `s_campaigns_mailaddresses` WHERE `id` = ?",
            array($addressId));

        return $email;
    }

    /**
     * Helper method that checks if a request is from the backend module
     *
     * @param $request
     * @return bool
     */
    private function isBackendChange($request)
    {
        $result = false;
        if (!empty($request) && ($request instanceof \Enlight_Controller_Request_RequestHttp)) {
            $result = ($request->getModuleName() == 'backend');
        }

        return $result;
    }
}
