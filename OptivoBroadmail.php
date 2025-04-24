<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail;

use Doctrine\ORM\Tools\SchemaTool;
use OptivoBroadmail\Models\ErrorQueue\ErrorQueue;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Components\Plugin\XmlPluginInfoReader;
use Shopware\Models\Config\Element;
use Shopware\Models\Mail\Mail;
use Shopware\Models\ProductFeed\ProductFeed;
use Shopware\Models\Shop\Repository as ShopRepository;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerBuilder;


/**
 * This class provides the boostrap functionality required by Shopware.
 */
class OptivoBroadmail extends \Shopware\Components\Plugin
{
    const API_VERSION = '1.1.5';

    const OPTIVO_ERROR_REPORT_MAILMODEL = 'sOPTIVOERRORREPORT';

    /**
     * @inheritdoc
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function install(InstallContext $context)
    {
        $this->cleanupPreviousInstallation();
        $this->createSchema();
        $feedId = $this->createProductFeed();
        $this->createAttributes();
        $this->createMailTemplates();
        $this->pluginConfiguration($feedId);
        parent::install($context);
    }

    /**
     * @param UpdateContext $context
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function update(UpdateContext $context)
    {
        if (version_compare($context->getCurrentVersion(), '1.2.0', '<')) {
            // Update optivo mail attributes
            $this->createAttributes();
        }
        parent::update($context);
    }

    /**
     * Fail-safe cleanup method used to remove existing broadmail model data on plugin installation
     * - Addresses issues with incomplete plugin uninstall and conflicts with previous versions
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    private function cleanupPreviousInstallation() {
        $this->removeSchema();
        $this->removeFeeds();
        try {
            $this->deleteAttributes();
        } catch (\Exception $e) {
            // Continue if any of the attribute cleanup operations failed (attributes do not exist)
            $metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
            $metaDataCache->deleteAll();
        }
    }

    /**
     * Creates database tables based on doctrine models
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    private function createSchema()
    {
        $tool = new SchemaTool($this->container->get('models'));
        $classes = [
            $this->container->get('models')->getClassMetadata(ErrorQueue::class),
        ];
        $tool->createSchema($classes);
    }

    /**
     * @return string
     */
    public function createProductFeed()
    {
        $db = Shopware()->Db();
        $sql = file_get_contents($this->getPath() . '/SQL/optivo_product_feed.sql');
        $db->query($sql, array('hash' => md5(uniqid(rand(), true))));

        return $db->lastInsertId();
    }

    /**
     * Create custom attributes
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    private function createAttributes()
    {
        $service = $this->container->get('shopware_attribute.crud_service');

        $service->update('s_core_config_mails_attributes', 'optivo_content', 'text');
        $service->update('s_core_config_mails_attributes', 'optivo_enable', 'boolean');
        $service->update('s_core_config_mails_attributes', 'optivo_authcode', 'string');
        $service->update('s_core_config_mails_attributes', 'optivo_bmMailId', 'string');

        $metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();
        $this->container->get('models')->generateAttributeModels(['s_core_config_mails_attributes']);
    }

    /**
     * Helper method to create optivo email templates
     *
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createMailTemplates()
    {
        $db = Shopware()->Db();

        $sqlsorder = file_get_contents($this->getPath() . '/SQL/mail_template_sorder.sql');
        $sqlsconfirm = file_get_contents($this->getPath() . '/SQL/mail_template_sconfirm.sql');

        try {
            $db->exec($sqlsorder);
            $db->exec($sqlsconfirm);
        } catch (\Exception $e) {
            // continue if mail templates cannot be imported
        }

        $mailModel = Shopware()->Models()->getRepository(Mail::class)
            ->findOneBy(['name' => static::OPTIVO_ERROR_REPORT_MAILMODEL]);
        if (null === $mailModel) {
            $mailModel = new Mail();
            $mailModel->setName(static::OPTIVO_ERROR_REPORT_MAILMODEL);
            $mailModel->setFromName('{config name=shopName}');
            $mailModel->setFromMail('{config name=mail}');
            $mailModel->setSubject('{$subject}');
            $mailModel->setContent('{$errorStatus}' . PHP_EOL . '{$errorMessage}' . PHP_EOL);
            $mailModel->setMailtype(-1);//will be hidden so it can't be edited on the email templates backend app
            Shopware()->Models()->persist($mailModel);
            Shopware()->Models()->flush();
        }
    }

    /**
     * @param $feedId
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function pluginConfiguration($feedId)
    {
        /** @var ProductFeed $feed */
        $feed = Shopware()->Models()->getRepository(ProductFeed::class)->find($feedId);
        if ($feed) {
            $mainShopRoute = $this->getShopRoute();

            // At the moment is not possible to add options on config.xml, so we need to add them here
            $exportLinkConfigElement = Shopware()->Models()->getRepository(Element::class)->findOneBy(['name' => 'exportLink']);
            $exportLinkConfigElement->setValue($mainShopRoute . '/backend/export/index/broadmail.csv?feedID=' . $feedId . '&hash=' . $feed->getHash());
        }

        Shopware()->Models()->flush();
    }

    /**
     * @param null $shopId
     * @return null|string
     */
    private function getShopRoute($shopId = null)
    {
        /** @var ShopRepository $shopRepository */
        $shopRepository = Shopware()->Models()->getRepository(Shop::class);
        /** @var \Shopware\Models\Shop\Shop $shop */
        $shop = null;
        if ($shopId) {
            $shop = $shopRepository->getActiveById($shopId);
        } else {
            $shop = $shopRepository->getActiveDefault();
        }
        if ($shop) {
            if ($shop->getSecure()) {
                return 'https://' . $shop->getHost() . $shop->getBaseUrl();
            }

            return 'http://' . $shop->getHost() . $shop->getBaseUrl();
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function uninstall(UninstallContext $context)
    {
        $this->uninstallMailTemplates();
        $this->removeFeeds();
        $this->removeSchema();
        try {
            $this->deleteAttributes();
        } catch (\Exception $e) {
            // Continue if any of the attribute cleanup operation failed (attributes do not exist)
            /* @var  */
            $metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
            $metaDataCache->deleteAll();
        }

        parent::uninstall($context);
    }

    /**
     * Remove database tables based on doctrine models
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    private function removeSchema()
    {
        $tool = new SchemaTool($this->container->get('models'));
        $classes = [
            $this->container->get('models')->getClassMetadata(ErrorQueue::class),
        ];
        $tool->dropSchema($classes);
    }

    /**
     * Delete optivo® broadmail custom attributes
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    private function deleteAttributes()
    {
        $service = $this->container->get('shopware_attribute.crud_service');
        $service->delete('s_core_config_mails_attributes', 'optivo_content', false);
        $service->delete('s_core_config_mails_attributes', 'optivo_enable', false);
        $service->delete('s_core_config_mails_attributes', 'optivo_authcode', false);
        $service->delete('s_core_config_mails_attributes', 'optivo_bmMailId', false);

        $metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();
        $this->container->get('models')->generateAttributeModels(['s_core_config_mails_attributes']);
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function build(ContainerBuilder $container)
    {
        // Set plugin metadata
        $container->setParameter('optivo_broadmail.plugin_dir', $this->getPath());
        $container->setParameter('optivo_broadmail.plugin_name', $this->getName());

        /** @var XmlPluginInfoReader $infoReader */
        $infoReader = $container->get('shopware.plugin_xml_plugin_info_reader');
        $pluginMeta = $infoReader->read($this->getPath() . '/plugin.xml');
        $pluginMeta['apiversion'] = self::API_VERSION;
        $container->setParameter('optivo_broadmail.plugin_metadata', $pluginMeta);

        parent::build($container);
    }

    /**
     * Uninstall optivo error report email templates
     */
    private function uninstallMailTemplates()
    {
        $mailModel = Shopware()->Models()->getRepository(Mail::class)
            ->findOneBy(['name' => static::OPTIVO_ERROR_REPORT_MAILMODEL]);
        if ($mailModel) {
            Shopware()->Models()->remove($mailModel);
            Shopware()->Models()->flush();
        }
    }

    /**
     * Helper method to remove feeds created by optivo® broadmail
     */
    private function removeFeeds()
    {
        Shopware()->Db()->query("DELETE FROM `s_export` WHERE `name` = ?", array('optivo® broadmail'));
    }
}
