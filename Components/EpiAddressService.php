<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */
namespace OptivoBroadmail\Components;

use Shopware\Bundle\AccountBundle\Service\AddressService;
use Shopware\Bundle\AccountBundle\Service\AddressServiceInterface;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Shopware\Components\Logger;


/**
 * This is a decorator for the address service. The main goal of decorated
 * services is to transfer modified address data (restricted to default
 * billing address) to Episerver Campaign.
 *
 * @package OptivoBroadmail\Subscriber
 */
class EpiAddressService implements AddressServiceInterface
{
    private $decoratedService;
    private $broadmailAPI;
    private $logger;

    public function __construct(AddressServiceInterface $service, BroadmailAPI $bm, Logger $logger)
    {
        $this->decoratedService = $service;
        $this->broadmailAPI = $bm;
        $this->logger = $logger;
    }

    /**
     * @param Address  $address
     * @param Customer $customer
     */
    public function create(Address $address, Customer $customer) {
        // first call decorated service, since data are updated and
        // validated there. validation exception should stop the flow.
        $this->decoratedService->create($address, $customer);
        if($this->isUpdateNeeded($address)) {
            $this->updateAddress($address);
        }
    }

    /**
     * @param Address $address
     */
    public function update(Address $address) {
        $this->decoratedService->update($address);
        if($this->isUpdateNeeded($address)) {
            $this->updateAddress($address);
        }
    }

    /**
     * @param Address $address
     */
    public function delete(Address $address) {
        // nothing to do, since default billing address cannot be deleted
        // by logic of decorated service.
        $this->decoratedService->delete($address);
    }

    /**
     * Sets the address to the default billing address in the customer model
     *
     * @param Address $address
     */
    public function setDefaultBillingAddress(Address $address) {
        $this->decoratedService->setDefaultBillingAddress($address);
        $this->updateAddress($address);
    }

    /**
     * Sets the address to the default shipping address in the customer model
     *
     * @param Address $address
     */
    public function setDefaultShippingAddress(Address $address) {
        // nothing to do for this add-on, since we do not transfer
        // shipping address, should all be done by decorated service.
        $this->decoratedService->setDefaultShippingAddress($address);
    }

    /**
     * Checks, if the customer is an active subscriber and if it is the
     * billing address. Otherwise, customer is not in Episerver Campaign
     * or it is not the billing address.
     *
     * @param Address $address
     * @return bool
     */
    private function isUpdateNeeded(Address $address) {
        if( ($address->getCustomer()->getDefaultBillingAddress()->getId() != $address->getId()))
        {
            return false;
        }
        return true;
    }

    /**
     * Updates relevant data in Episerver Campaign. It transfers Address
     * and customer data.
     *
     * @param Address $address
     *
     */
    private function updateAddress(Address $address) {
        $customer = $address->getCustomer();
        $email = $customer->getEmail();

        $plugInName = Shopware()->Container()->getParameter('optivo_broadmail.plugin_name');
        $authCode = Shopware()->Config()->getByNamespace($plugInName, 'optivoAuthCode');

        $data['bmRecipientId'] = $email;
        $data['authCode'] = $authCode;
        $data['salutation'] = $customer->getSalutation();
        $data['firstname'] = $customer->getFirstname();
        $data['lastname'] = $customer->getLastname();
        $data['street'] =
            $customer->getDefaultBillingAddress()->getStreet() . ' ' .
            $customer->getDefaultBillingAddress()->getAdditionalAddressLine1() . ' ' .
            $customer->getDefaultBillingAddress()->getAdditionalAddressLine2();
        $data['zip'] = $customer->getDefaultBillingAddress()->getZipcode();
        $data['city'] = $customer->getDefaultBillingAddress()->getCity();
        $attribute = $customer->getDefaultBillingAddress()->getAttribute();
        if(isset($attribute)) {
            $result['text1'] = $attribute->getText1();
            $result['text2'] = $attribute->getText2();
            $result['text3'] = $attribute->getText3();
            $result['text4'] = $attribute->getText4();
            $result['text5'] = $attribute->getText5();
            $result['text6'] = $attribute->getText6();
        } else {
            $result['text1'] = "";
            $result['text2'] = "";
            $result['text3'] = "";
            $result['text4'] = "";
            $result['text5'] = "";
            $result['text6'] = "";
        }
        $data['telefon'] = $customer->getDefaultBillingAddress()->getPhone();
        $data['company'] = $customer->getDefaultBillingAddress()->getCompany();
        $data['department'] = $customer->getDefaultBillingAddress()->getDepartment();
        $data['customer-group'] = $customer->getGroup()->getName();
        $data['shop-id'] = empty($shopId) ? $customer->getShop()->getId() : $shopId;
        $data['shop'] = empty($shopName) ? $customer->getShop()->getName() : $shopName;
        $data['vatid'] = $customer->getDefaultBillingAddress()->getVatId();
        $data['country'] = $customer->getDefaultBillingAddress()->getCountry()->getIsoName();
        $result = $this->broadmailAPI->updateFields($email, $data);
        return $result;
    }
}
?>
