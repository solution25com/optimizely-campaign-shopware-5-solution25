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
 * Customer Subscriber
 *
 * Configure here the Event Subscriptions for any Backend scope
 * e.g. adding additional smarty views or assigning a variable.
 *
 * @package OptivoBroadmail\Subscriber
 */
class CustomerSubscriber extends BaseSubscriber
{
    private $oldEmail;
    /**
     * @var NewsletterSubscriber
     */
    private $subscriberService;

    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        NewsletterSubscriberService $subscriberService
    ) {
        $this->subscriberService = $subscriberService;

        parent::__construct($container, $logger);
    }

    /**
     * Helper method to define event subscriptions
     *
     * @return array Structure with event subscriptions "<event> => array(<listener>, ..., <listener_n>)"
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware\Models\Customer\Customer::postRemove' => 'onCustomerPostRemove',
            'Shopware\Models\Customer\Customer::preUpdate' => 'onCustomerPreUpdate',
            'Shopware\Models\Customer\Customer::postUpdate' => 'onCustomerPostUpdate',
        ];
    }

    /**
     * customer was deleted
     *
     * @param \Enlight_Event_EventArgs $arguments
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function onCustomerPostRemove(\Enlight_Event_EventArgs $arguments)
    {
        /** @var Customer $model */
        $model = $arguments->get('entity');
        if (!empty($model) && ($model instanceof Customer)) {
            $email = $model->getEmail();
            $this->subscriberService->unsubscribe($email);
            $this->subscriberService->removeShopwareRecipient($email);
        }
    }

    /**
     * this function stores old email value to use it for re-subscribe process
     *
     * @param \Enlight_Event_EventArgs $arguments
     */
    public function onCustomerPreUpdate(\Enlight_Event_EventArgs $arguments)
    {
        $model = $arguments->get('entity');
        $this->oldEmail = $this->getOldEmail($model->getId());
    }

    /**
     * @param \Enlight_Event_EventArgs $arguments
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function onCustomerPostUpdate(\Enlight_Event_EventArgs $arguments)
    {
        /** @var Customer $user */
        $user = $arguments->get('entity');
        $newEmail = $user->getEmail();
        $oldEmail = $this->oldEmail;
        if (
            mb_strtolower($oldEmail) != mb_strtolower($newEmail) &&
            $this->subscriberService->wasOldEmailSubscribed($oldEmail)
        ) {
            $this->subscriberService->resubscribe($oldEmail, $newEmail, $user);
        }

        $shop = $this->subscriberService->loadShop($user->getId());

        $userData = $this->subscriberService->getCustomerData($user, $shop->getName());
        $this->subscriberService->updateFields($newEmail, $userData, $shop);
    }

    /**
     * get old user email (user was changed in backend). plain sql, because doctrine has already new email
     *
     * @param int $userId
     * @return string
     */
    private function getOldEmail($userId)
    {
        $email = Shopware()->Db()->fetchOne('SELECT `email` FROM `s_user` WHERE `id` = ?', array($userId));
        return $email;
    }
}
