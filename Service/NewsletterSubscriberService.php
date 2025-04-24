<?php

namespace OptivoBroadmail\Service;

use OptivoBroadmail\Components\BroadmailAPI;
use OptivoBroadmail\Helper\BroadmailAdditionalData;
use Shopware\Models\CommentConfirm\CommentConfirm;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Newsletter\Address;
use Shopware\Models\Shop\Shop;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NewsletterSubscriberService
{
    /**
     * @var \Shopware\Components\Model\ModelManager $em
     */
    private $em;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var string */
    protected $pluginDir;

    /** @var string */
    protected $pluginName;

    /** @var  array */
    protected $pluginMetadata;

    /**
     * @var BroadmailAPI
     */
    private $broadmailApiClient;

    /**
     * @inheritdoc
     *
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger, BroadmailAPI $broadmailApiClient)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->em = $this->getContainer()->get('models');

        // Init plugin info
        $this->pluginDir = $this->container->getParameter('optivo_broadmail.plugin_dir');
        $this->pluginName = $this->container->getParameter('optivo_broadmail.plugin_name');
        $this->pluginMetadata = $this->container->getParameter('optivo_broadmail.plugin_metadata');
        $this->broadmailApiClient = $broadmailApiClient;
    }

    /**
     * Subscribe Broadmail
     *
     * @param Customer $user
     * @param string $email
     * @param array $additionalData
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     * @throws \Enlight_Exception
     */
    public function subscribe($user, $email, $additionalData = array())
    {
        if (!empty($user) && ($user instanceof Customer)) {
            $shop = $this->loadShop($user->getId());
        } else {
            $shop = $this->getDefaultShop();
        }

        $customerData = $this->getCustomerData($user, $shop->getName(), $shop->getId());
        $additionalDataHelper = new BroadmailAdditionalData();
        $additionalData = $additionalDataHelper->addAdditionalData($additionalData, array());
        $data = array_merge($customerData, $additionalData);

        // Add the shop-id if its missing
        if (empty($data['shop-id'])) {
            $data['shop-id'] = $shop->getId();
        }

        // Add the shop name if its missing
        if (empty($data['shop'])) {
            $data['shop'] = $shop->getName();
        }

        $data['authCode'] = Shopware()->Config()->getByNamespace($this->getPluginName(), 'optivoAuthCode');
        $data['bmOptInId'] = Shopware()->Config()->getByNamespace($this->getPluginName(), 'optivoOptInId');

        $dataForOptIn = $customerData;
        $dataForOptIn['newsletter'] = $email;
        $hash = $this->createAndInsertHashCode($dataForOptIn);
        if ($hash) {
            $data['customer-id'] = $hash;
            unset($dataForOptIn);
        }
        $data['bmRecipientId'] = $email;
        $version = $this->getPluginMetadata()['version'];
        $data['bmOptinSource'] = 'Shopware-Integration/' . $this->findMainShopName() . '/Version:' . $version;

        return $this->broadmailApiClient->subscribe($email, $data);
    }

    /**
     * Helper function to unsubscribe an email
     *
     * If "email" belongs to a customer, the customer's shop scope will be used to retrieve
     * configuration data
     *
     * @param $email
     * @return array
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function unsubscribe($email)
    {
        $customer = Shopware()->Models()->getRepository(Customer::class)->findOneBy(['email' => $email]);
        if (!empty($customer)) {
            Shopware()->Config()->setShop($customer->getShop());
        }

        $data['authCode'] = Shopware()->Config()->getByNamespace($this->getPluginName(), 'optivoAuthCode');
        unset($data['bmMailingId'], $data['bmOptinSource']);

        return $this->broadmailApiClient->unsubscribe($email, $data);
    }

    /**
     * Helper function to resubscribe an existing user with a different email (email change)
     *
     * @param $oldEmail
     * @param $newEmail
     * @param $user
     */
    public function resubscribe($oldEmail, $newEmail, $user)
    {
        // unsubscribe old email
        $this->unsubscribe($oldEmail);
        $this->removeShopwareRecipient($oldEmail);
        $this->subscribe($user, $newEmail);
    }

    /**
     * Helper method that fetches a customer's shop object by customer database ID
     *
     * @param int $customerId
     * @return \Shopware\Models\Shop\Shop
     * @throws \Doctrine\ORM\ORMException
     */
    public function loadShop($customerId)
    {
        $shop = null;
        $userShop = Shopware()->Db()->fetchRow('SELECT `subshopID`, `language` FROM `s_user` WHERE id = ?',
            array($customerId));
        if (!empty($userShop)) {
            $language = $userShop['language'];
            $subshopID = $userShop['subshopID'];
            /** @var $builder \Shopware\Components\Model\QueryBuilder */
            $builder = Shopware()->Models()->createQueryBuilder();
            $builder->select('shop')
                ->from('Shopware\Models\Shop\Shop', 'shop')
                ->where('shop.mainId IS NULL and shop.id = :localeID1')
                ->orWhere('shop.mainId = :mainId and shop.id = :localeID2')
                ->setParameter(':localeID1', $language)
                ->setParameter(':localeID2', $language)
                ->setParameter(':mainId', $subshopID);
            $query = $builder->getQuery();
            $shop = $query->getOneOrNullResult();
        }
        if (!empty($shop)) {
            Shopware()->Config()->setShop($shop);
        }

        return $shop;
    }

    /**
     * @param Customer $customer
     * @param string $shopName
     * @param int $shopId
     * @return array
     * @throws \Exception
     */
    public function getCustomerData($customer, $shopName = '', $shopId = null)
    {
        $result = array();

        if (empty($customer) || !($customer instanceof Customer)) {
            return $result;
        }

        $result['salutation'] = $customer->getSalutation();
        $result['firstname'] = $customer->getFirstname();
        $result['lastname'] = $customer->getLastname();
        $result['street'] =
            $customer->getDefaultBillingAddress()->getStreet() . ' ' .
            $customer->getDefaultBillingAddress()->getAdditionalAddressLine1() . ' ' .
            $customer->getDefaultBillingAddress()->getAdditionalAddressLine2();
        $result['zip'] = $customer->getDefaultBillingAddress()->getZipcode();
        $result['city'] = $customer->getDefaultBillingAddress()->getCity();
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
		if($customer->getBirthday() !== null) {
			$result['date-of-birth'] = $customer->getBirthday()->format('Y-m-d H:i:s');
		}
        $result['telefon'] = $customer->getDefaultBillingAddress()->getPhone();
        $result['company'] = $customer->getDefaultBillingAddress()->getCompany();
        $result['department'] = $customer->getDefaultBillingAddress()->getDepartment();
        $result['customer-group'] = $customer->getGroup()->getName();
        $result['shop-id'] = empty($shopId) ? $customer->getShop()->getId() : $shopId;
        $result['shop'] = empty($shopName) ? $customer->getShop()->getName() : $shopName;
        $result['vatid'] = $customer->getDefaultBillingAddress()->getVatId();
        $result['country'] = $customer->getDefaultBillingAddress()->getCountry()->getIsoName();

        return $result;
    }

    /**
     * remove email from the newsletter recipient list in shopware
     *
     * @param string $email
     * @return bool
     */
    public function removeShopwareRecipient($email)
    {
        if (empty($email)) {
            return false;
        }

        $groupId = $this->getDefaultGroupIdForNewsletter();
        $address = Shopware()->Models()->getRepository('Shopware\Models\Newsletter\Address')
            ->findOneBy(array('email' => $email, 'groupId' => $groupId));

        if (is_null($address)) {
            return false;
        }

        if ($address instanceof Address) {
            Shopware()->Models()->remove($address);
            Shopware()->Models()->flush($address);

            return true;
        }
        return false;
    }

    /**
     * returns groupId for the newsletter subscription
     *
     * @return int
     */
    public function getDefaultGroupIdForNewsletter()
    {
        $result = Shopware()->Config()->get("sNEWSLETTERDEFAULTGROUP");
        $result = empty($result) ? 1 : (int)$result;

        return $result;
    }

    /**
     * returns if $oldEmail was subscribed for the newsletter (shopware subscription)
     *
     * @param string $oldEmail
     * @return bool
     */
    public function wasOldEmailSubscribed($oldEmail)
    {
        $address = $this->em->getRepository('Shopware\Models\Newsletter\Address')
            ->findOneBy(array('email' => $oldEmail));

        return ($address instanceof Address);
    }

    /**
     * Updates subscription data
     *
     * @param string $email
     * @param array $userData
     * @param Shop $shop
     * @return array
     */
    public function updateFields($email, $userData, $shop)
    {
        $data = $userData;
        $data['bmRecipientId'] = $email;
        $data['authCode'] = Shopware()->Config()->getByNamespace($this->getPluginName(), 'optivoAuthCode');

        $version = $this->getPluginMetadata()['version'];
        $data['bmOptinSource'] = 'Shopware-Integration/' . $this->getShopName($shop) . '/Version:' . $version;

        return $this->broadmailApiClient->updateFields($email, $data);
    }

    /**
     * Create a new recipient
     *
     * @param string $email
     * @param int $groupId
     * @return Address|null
     */
    public function createShopwareRecipient($email, $groupId, $confirmed = false)
    {
        if ($email === null || $groupId === null) {
            return null;
        }

        if ($this->isEmailAlreadySubscribed($email)) {
            return null;
        }

        $recipient = new Address();
        if ($recipient instanceof Address) {
            $recipient->setGroupId($groupId);
            $recipient->setEmail($email);
            $recipient->setIsCustomer(false);
            if ($confirmed) {
                $recipient->setDoubleOptinConfirmed(new \DateTime());
            }
            Shopware()->Models()->persist($recipient);
            Shopware()->Models()->flush();
        }

        return $recipient;
    }

    /**
     * @param string $email
     * @return bool
     */
    public function isEmailAlreadySubscribed($email)
    {
        $address = Shopware()->Models()->getRepository('Shopware\Models\Newsletter\Address')
            ->findOneBy(array('email' => $email));

        return (!empty($address) && ($address instanceof Address));
    }

    /**
     * @param string $email
     * @return bool
     */
    public function getNewsletterSubscriber($email)
    {
        return Shopware()->Models()->getRepository('Shopware\Models\Newsletter\Address')
            ->findOneBy(['email' => $email]);
    }

    /**
     * @param int $userId
     * @return null|Customer
     * @throws \Doctrine\ORM\ORMException
     */
    public function getUserById($userId)
    {
        $user = null;
        if (!empty($userId)) {
            $user = $this->em->getReference('Shopware\Models\Customer\Customer', $userId);
        }

        return $user;
    }

    /**
     * create and save hash value to the db
     *
     * @param $userData
     * @return string
     */
    protected function createAndInsertHashCode($userData)
    {
        $result = '';
        $hash = md5(uniqid(rand()));
        $data = serialize($userData);
        $confirm = new CommentConfirm();

        if ($confirm instanceof CommentConfirm) {
            $confirm->setData($data);
            $confirm->setHash($hash);
            $confirm->setCreationDate(new \DateTime());
            Shopware()->Models()->persist($confirm);
            Shopware()->Models()->flush($confirm);
            $result = $hash;
        }

        return $result;
    }

    /**
     * Helper method to get the shop name for current scope
     *
     * @param $shop
     * @return string
     */
    private function getShopName($shop)
    {
        if (!empty($shop)) {
            $shopName = $shop->getName();
        } else {
            $shopName = $this->findMainShopName();
        }

        return $shopName;
    }

    /**
     * Helper method to find the main shop name
     * - To keep legacy behaviour, "Backend" is used as fall-back name since there
     * is no Shop scope in Shopware backend.
     * - In the future this can be changed to return the active default shop name instead.
     *
     * @return string
     */
    private function findMainShopName()
    {
        if (empty($shop = Shopware()->Shop())) {
            $shopName = 'Backend';
        } elseif (empty($mainShop = $shop->getMain())) {
            $shopName = $shop->getName();
        } else {
            $shopName = $mainShop->getName();
        }

        return $shopName;
    }

    /**
     * Helper method to retrieve the active default shop for the Shopware installation
     * Sets the configuration scope
     *
     * @return Shop $shop
     */
    private function getDefaultShop()
    {
        /* @var Shop $shop */
        $shop = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop')->getActiveDefault();
        $shop->registerResources();
        Shopware()->Config()->setShop($shop);

        return $shop;
    }

    /**
     * @return ContainerInterface
     */
    private function getContainer()
    {
        return $this->container;
    }

    /**
     * @return string
     */
    private function getPluginDir()
    {
        return $this->pluginDir;
    }

    /**
     * @return string
     */
    private function getPluginName()
    {
        return $this->pluginName;
    }

    /**
     * @return array
     */
    private function getPluginMetadata()
    {
        return $this->pluginMetadata;
    }
}
