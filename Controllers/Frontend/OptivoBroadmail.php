<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

use OptivoBroadmail\Service\NewsletterSubscriberService;
use Shopware\Models\CommentConfirm\CommentConfirm;
use Shopware\Models\Newsletter\Address;

/**
 * Class Shopware_Controllers_Frontend_OptivoBroadmail. This controller is
 * called if a user clicks on a subscription link within an email (DOI) sent
 * by Episerver Campaign.
 */
class Shopware_Controllers_Frontend_OptivoBroadmail extends Enlight_Controller_Action
{
    /**
     * @var NewsletterSubscriberService
     */
    private $subscriberService;

    public function init()
    {
        $this->subscriberService = Shopware()->Container()->get('optivo_broadmail.subscriber_service');
    }

    /**
     * Handle index frontend requests. The request should at least contain
     * the hash and a shop-id set by Episerver Campaign. With this information
     * this controller can redirect to a proper sub- or language shop and show
     * a suitable message with correct language to the user.
     */
    public function indexAction()
    {
        $view = $this->View();
        $request = $this->Request();
        $hash = $request->getParam('hash');

        $view->loadTemplate('frontend/plugins/OptivoBroadmail/templates/confirmDoubleOptIn.tpl');
        /* @var \Psr\Log\LoggerInterface $logger */
        $logger = Shopware()->Container()->get('optivo_broadmail.logger');

        // Redirect to user shop if conditions apply
        if ($this->setUserShopRedirectUrl() === true) {
            return;
        }

        /** @var CommentConfirm $confirmation */
        $confirmation = $this->getConfirmationByHash($hash);
        if (empty($confirmation)) {
            $view->assign('recipientNoConfirmationHash', true);
            $logger->warning('Shopware_Controllers_Frontend_OptivoBroadmail.indexAction: Request doesnt contain a confirmation hash!');

            return;
        }

        $email = $this->getEmailFromHash($hash);
        $groupId = $this->subscriberService->getDefaultGroupIdForNewsletter();

        if ($this->subscriberService->isEmailAlreadySubscribed($email)) {
            $recipient = $this->subscriberService->getNewsletterSubscriber($email);
            if ($recipient instanceof Address) {
                if ($recipient->getDoubleOptinConfirmed() instanceof \DateTime) {
                    $view->assign('recipientAlreadyRegistered', true);
                    $logger->info("Shopware_Controllers_Frontend_OptivoBroadmail.indexAction: Email already subscribed: " . $email);
                    return;
                }

                $recipient->setDoubleOptinConfirmed(new \DateTime());
                Shopware()->Models()->persist($recipient);
                Shopware()->Models()->flush();

                $view->assign('recipientSuccessfulCreated', true);
                $view->assign('recipient', Shopware()->Models()->toArray($recipient));
                return;
            }
        }

        $recipient = $this->subscriberService->createShopwareRecipient($email, $groupId, true);
        if ($recipient instanceof Address) {
            $view->assign('recipientSuccessfulCreated', true);
            $view->assign('recipient', Shopware()->Models()->toArray($recipient));
            return;
        }

        $view->assign('recipientSuccessfulCreated', false);
    }

    /**
     * Method that handles user shop redirection
     *
     * @return bool true if redirected, false otherwise
     */
    private function setUserShopRedirectUrl()
    {
        $request = $this->Request();
        $response = $this->Response();
        $currentShop = Shopware()->Shop();
        /* @var \Psr\Log\LoggerInterface $logger */
        $logger = Shopware()->Container()->get('optivo_broadmail.logger');
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');

        // Check if there's a valid "shop-id" set in the request
        if (
            !empty($targetShopId = $request->getParam('shop-id')) &&
            is_numeric($targetShopId)
        ) {
            $targetShop = $repository->findOneBy(array('id' => $targetShopId));

            // If not, check if "shop" (shop-name legacy support) is set as a fall-back
        } elseif (!empty($targetShopName = $request->getParam('shop'))) {
            $targetShop = $repository->findOneBy(array('name' => $targetShopName));
        }

        // Redirect to customer's shop if its not the current one
        if (
            !empty($targetShop) &&
            $this->shouldRedirectToLanguageShop($targetShop, $currentShop)
        ) {
            $redirectUrl = $this->getRedirectUrlToLanguageShop($targetShop, $currentShop, $request, $response);
            $response->setRedirect($redirectUrl);
            $logger->info(sprintf("Shopware_Controllers_Frontend_OptivoBroadmail.setUserShopRedirectUrl: Redirected, URL to language shop: \"%s\".", $redirectUrl));

            return true;
        }
        return false;
    }

    /**
     * Determine if user should/can be redirected to target shop
     *
     * @param \Shopware\Models\Shop\Shop $currentShop
     * @param \Shopware\Models\Shop\Shop $targetShop
     * @return bool true if redirection should occur, false otherwise
     */
    private function shouldRedirectToLanguageShop($targetShop, $currentShop)
    {
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');

        if (empty($targetShop)) {
            return false;
        }

        if (($targetShop instanceof \Shopware\Models\Shop\Shop) === false) {
            return false;
        }

        $newShop = $repository->getActiveById($targetShop->getId());
        if (empty($newShop)) {
            return false;
        }

        if (($newShop instanceof \Shopware\Models\Shop\Shop) === false) {
            return false;
        }

        //
        // Check if requested shop host is different than current shop host
        //

        if (
            $newShop->getHost() !== null &&
            $newShop->getHost() !== $currentShop->getHost()
        ) {
            return true;
        }
        if (
            $newShop->getBaseUrl() !== null &&
            $newShop->getBaseUrl() !== $currentShop->getBaseUrl()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Helper method to build customer shop redirect URL
     *
     * @param \Shopware\Models\Shop\Shop $targetShop
     * @param \Shopware\Models\Shop\Shop $currentShop
     * @param Enlight_Controller_Request_RequestHttp $request
     * @param Enlight_Controller_Response_ResponseHttp $response
     * @return string
     */
    private function getRedirectUrlToLanguageShop($targetShop, $currentShop, $request, $response)
    {
        // Check for host
        /* @var string $targetHost */
        if (empty($targetHost = $targetShop->getHost())) {
            // Language shops don't have an host defined so we fallback to the language shop's main shop host
            $targetHost = $targetShop->getMain()->getHost();
        }

        $path = rtrim($targetShop->getBasePath(), '/') . '/';
        $response->setCookie('shop', $targetShop->getId(), 0, $path);

        // Remove "this" shop base URL from the request URI
        if (empty($baseUrl = $currentShop->getBaseUrl())) {
            $baseUrl = '';
        } else {
            $baseUrl .= '/';
        }
        $httpQuery = str_replace($baseUrl, '', $request->getRequestUri());
        $httpQuery = ltrim($httpQuery, '/');

        // Build final redirect URL
        $url = sprintf('%s://%s%s%s%s',
            $request->getScheme(),
            $targetHost,
            $targetShop->getBaseUrl(),
            '/',
            $httpQuery
        );

        return $url;
    }

    /**
     * @param string $hash
     * @return CommentConfirm
     */
    public function getConfirmationByHash($hash)
    {
        $query = Shopware()->Models()->getRepository('Shopware\Models\CommentConfirm\CommentConfirm')
            ->getConfirmationByHashQuery($hash);
        $query->setMaxResults(1);
        $confirmation = $query->getOneOrNullResult();

        return $confirmation;
    }

    /**
     * @param $hash
     * @return mixed|string
     */
    private function getEmailFromHash($hash)
    {
        $email = '';
        $confirmation = $this->getConfirmationByHash($hash);
        if (!empty($confirmation) && ($confirmation instanceof CommentConfirm)) {
            $data = $confirmation->getData();
            $email = $this->getParsedData($data, 'newsletter');
        }

        return $email;
    }

    /**
     * @param string|array $data serialized string or array
     * @param string $field field to retrieve (optional)
     * @return mixed
     */
    private function getParsedData($data, $field = '')
    {
        $result = $data;
        $data = unserialize($data);
        if (is_array($data) && !empty($field)) {
            $result = $data[$field];
        }

        return $result;
    }

    /**
     * Unsubscribe request
     */
    public function unsubscribeAction()
    {
        $view = $this->View();
        $request = $this->Request();

        if ($this->setUserShopRedirectUrl() === true) {
            return;
        }

        $hash = $request->get('hash');
        $email = $this->getEmailFromHash($hash);
        $this->subscriberService->removeShopwareRecipient($email);
        $view->loadTemplate('frontend/plugins/OptivoBroadmail/templates/unsubscribe.tpl');
        $view->assign('recipientSuccessfulUnsubscribed', true);
    }

}
