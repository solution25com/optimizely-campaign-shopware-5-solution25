<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */
namespace OptivoBroadmail\Proxy;

use OptivoBroadmail\Helper\BroadmailAdditionalData;
use Psr\Log\LoggerInterface;

/**
 * Overwritten Mail class. This MailProxy sends transactional emails with
 * Infrastructure of Episerver. This requires configuration, sending protocol
 * is http. As a fallback (if nothing else is configured), this class sends
 * with parent methods.
 */
class MailProxy extends \Enlight_Components_Mail
{
    protected $optivoData;
    protected $shop;
    protected $templateName;
    protected $context;

    /**
     * send method, this method sends an email. This method doesn't generate
     * any notifications. If another add-on needs these, you should add
     * proper notifications.
     */
    public function send($transport = null)
    {
        $broadmailAPI = Shopware()->Container()->get('optivo_broadmail.api');
        $logger = Shopware()->Container()->get('optivo_broadmail.logger');

        $data = $this->getOptivoData();
        $context = $this->getContext();
        $userData = Shopware()->Modules()->Admin()->sGetUserData();

        $pluginName = Shopware()->Container()->getParameter('optivo_broadmail.plugin_name');
        $pluginMetadata = Shopware()->Container()->getParameter('optivo_broadmail.plugin_metadata');

        // send loop, for every recipient generate one email.
        foreach ($this->getTo() as $email) {
            // is an double-opt-in or confirmed email
            $isOptinMailing = !empty($data['optin-type']);
            $logger->debug('MailProxy value of "optin-type":' . $data['optin-type']); 
            if ($isOptinMailing) {
                $registerData = Shopware()->Front()->Request()->get('register');
                $additionalData = $registerData['additional'] ?? [];
                $additionalDataHelper = new BroadmailAdditionalData();
                $additionalData = $additionalDataHelper->addAdditionalData($additionalData, array());
                $data = array_merge($data, $additionalData);

                unset($data['optin-type']); // tidy up data that shouldn't be part of a rest call
                // enrich $data with information necessary for subscriptions
                $data['bmRecipientId'] = $email;
                // Add the shop-id if its missing
                if (empty($data['shop-id'])) {
                    $data['shop-id'] = $this->getShop();
                }

                $version = $pluginMetadata['version'];
                $data['bmOptinSource'] = 'Shopware-Integration/' . $data['shop'] . '/Version:' . $version;

                $_userData = $this->prepareCustomerData($context, $userData);
                $customerData = $this->mapCustomerData($context, $_userData);
                $data = array_merge($data, $customerData);

                // get auth tokens and ids for subscribing a user
                $data['authCode'] = Shopware()->Config()->getByNamespace($pluginName, 'optivoAuthCode');
                $data['bmOptInId'] = Shopware()->Config()->getByNamespace($pluginName, 'optivoOptInId');
                // $logger->debug('MailProxy: subscribe with OptInId: ' . $data['bmOptInId'] . ' and authcode: ' . $data['authCode']);
                $response = $broadmailAPI->subscribe($email, $data);
                $result = array('result' => 0, 'error' => 'unknown');
                if ($response['success'] === true) {
                    if ($response['message'] == 'ok') {
                        $result = array('result' => 1, 'message' => $response['message']);
                    } else {
                        if ($response['message'] == 'duplicate') {
                            $result = array('result' => 0, 'error' => 'duplicate');
                        }
                    }
                }
            } else {
                // send a transactional email
                $response = $broadmailAPI->sendtransactionmail($email, $data);
                if ($response['success'] === true) {
                    if (strpos(strtolower($response['message']), 'enqueued') === false) {
                        $response['success'] = false;
                    }
                }
            }
            // log an error if something went wrong.
            if ($response['success'] === false) {
                $logger->error('MailProxy.send: Broadmail API call was not successful: ' . $response['message']);
            }

            Shopware()->Front()->Request()->setParam('newsletterRegisterError', $result['error']);
            Shopware()->Front()->Request()->setParam('newsletterRegister', $result);
            return $result;
        }
    }

    /**
     * setOptivoData
     * sets all required fields for the transactionmail call
     *
     * @param array $data template fields
     */
    public function setOptivoData($data)
    {
        $this->optivoData = $data;
    }

    /**
     * getOptivoData
     * returns the optivoData
     *
     * @return array $data template fields
     */
    public function getOptivoData()
    {
        return $this->optivoData;
    }

    /**
     * setContext
     * sets the mail context
     *
     * @param array $context the context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * getContext
     * returns the context of the mail
     *
     * @return array the context of the mail
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * setShop
     * sets the shop
     *
     * @param integer $shop the shop id
     */
    public function setShop($shop)
    {
        $this->shop = $shop;
    }

    /**
     * getShop
     * returns the id og the shop
     *
     * @return integer the id of the shop
     */
    public function getShop()
    {
        return $this->shop;
    }

    /**
     * setTemplateName
     * sets the name of the template
     *
     * @param string $templateName the name of the mail template
     */
    public function setTemplateName($templateName)
    {
        $this->templateName = $templateName;
    }


    /**
     * getTemplateName
     * returns the name of the template
     *
     * @return string the name of the template
     */
    public function getTemplateName()
    {
        return $this->templateName;
    }

    /**
     * Helper function for NOTE TBD
     */
    private function mapCustomerData(array $context, $customerData)
    {
        $result = array();
        $salutation = $customerData['sUser']['salutation'];
        if (!empty($salutation)) {
            /** @var Shopware_Components_Snippet_Manager $snippets */
            $snippets = Shopware()->Container()->get('snippets');
            if ($salutation == 'mr') {
                $result['salutation'] = $snippets->getNamespace('backend/plugins/optivo')->get('OptivoSalutation' . $salutation,
                    'Herr', true);
            } else {
                if ($salutation == 'ms') {
                    $result['salutation'] = $snippets->getNamespace('backend/plugins/optivo')->get('OptivoSalutation' . $salutation,
                        'Frau', true);
                } else {
                    $result['salutation'] = $snippets->getNamespace('backend/plugins/optivo')->get('OptivoSalutation' . $salutation,
                        $salutation, true);
                }
            }
        }
        if (!empty($customerData['sUser']['firstname'])) {
            $result['firstname'] = $customerData['sUser']['firstname'];
        }
        if (!empty($customerData['sUser']['lastname'])) {
            $result['lastname'] = $customerData['sUser']['lastname'];
        }
        if (!empty($customerData['sUser']['street'])) {
            $result['street'] = $customerData['sUser']['street'];
        }
        if (!empty($customerData['sUser']['streetnumber'])) {
            $result['street'] = $result['street'] . ' ' . $customerData['sUser']['streetnumber'];
        }
        if (!empty($customerData['sUser']['zipcode'])) {
            $result['zip'] = $customerData['sUser']['zipcode'];
        }
        if (!empty($customerData['sUser']['city'])) {
            $result['city'] = $customerData['sUser']['city'];
        }
        if (!empty($customerData['sUser']['country'])) {
            $result['country'] = $customerData['sUser']['country'];
        }
        if (!empty($customerData['sUser']['customer-group'])) {
            $result['customer-group'] = $customerData['sUser']['customer-group'];
        }
        $hash = $this->getHashFromLink($context['sConfirmLink']);
        if ($hash) {
            $result['customer-id'] = $hash;
        }

        $additionalDataHelper = new BroadmailAdditionalData();
        $result = $additionalDataHelper->addAdditionalData($customerData['sUser'], $result);

        $result['Version'] = Shopware()->Container()->getParameter('optivo_broadmail.plugin_metadata')['apiversion'];

        return $result;
    }


    /**
     * try to find "sConfirmation" in the url $confirmationLink and get the hash value
     *
     * @param string $confirmLink
     * @return string
     */
    public function getHashFromLink($confirmLink)
    {
        $hash = '';
        $url = \parse_url($confirmLink);
        $urlPath = \explode('/', $url['path']);

        if ($key = \array_search('sConfirmation', $urlPath)) {
            $hash = $urlPath[$key + 1]; // next param after "sConfirmation" is the hash
        }
        return $hash;
    }

    private function prepareCustomerData($context, $sUserData)
    {
        $data = $context['sUser'];

        $front = Shopware()->Front();
        if (!empty($front)) {
            $req = $front->Request();
            if (!empty($req)) {
                $firstname = $req->getPost('firstname');
                $lastname = $req->getPost('lastname');
                $street = $req->getPost('street');
                $streetnumber = $req->getPost('streetnumber');
                $zip = $req->getPost('zip');
                $city = $req->getPost('city');

                if (!empty($firstname)) {
                    $data['firstname'] = $firstname;
                }
                if (!empty($lastname)) {
                    $data['lastname'] = $lastname;
                }
                if (!empty($street)) {
                    $data['street'] = $street;
                }
                if (!empty($streetnumber)) {
                    $data['street'] = $data['street'] . ' ' . $streetnumber;
                }
                if (!empty($zip)) {
                    $data['zip'] = $zip;
                }
                if (!empty($city)) {
                    $data['city'] = $city;
                }
            }
        }

        if (empty($data)) {
            $data['salutation'] = $sUserData['billingaddress']['salutation'];
            $data['firstname'] = $sUserData['billingaddress']['firstname'];
            $data['lastname'] = $sUserData['billingaddress']['lastname'];
            $data['street'] = $sUserData['billingaddress']['street'];
            $data['zipcode'] = $sUserData['billingaddress']['zipcode'];
            $data['city'] = $sUserData['billingaddress']['city'];
            if (!empty($sUserData['additional']['country']['countryen'])) {
                $data['country'] = $sUserData['additional']['country']['countryen'];
            }
            if (!empty($sUserData['additional']['user']['customergroup'])) {
                $customerGroup = Shopware()->Modules()->Export()->sGetCustomergroup($sUserData['additional']['user']['customergroup']);
                if (!empty($customerGroup['description'])) {
                    $data['customer-group'] = $customerGroup['description'];
                }
            }
        }
        $result['sUser'] = $data;
        return $result;
    }
}
