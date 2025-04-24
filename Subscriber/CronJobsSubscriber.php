<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Subscriber;

use OptivoBroadmail\Components\BroadmailAPI;
use OptivoBroadmail\Models\ErrorQueue\ErrorQueue;
use OptivoBroadmail\Models\ErrorQueue\Repository;
use OptivoBroadmail\OptivoBroadmail;
use Psr\Log\LoggerInterface;
use Shopware\Models\ProductFeed\ProductFeed;
use Shopware\Models\Shop\Shop;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use Shopware_Components_TemplateMail;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CronJobsSubscriber
 *
 * @package VotumMediaImport\Subscriber
 */

class CronJobsSubscriber extends BaseSubscriber
{
    /**
     * @var BroadmailAPI
     */
    private $broadmailApiClient;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, BroadmailAPI $broadmailApiClient)
    {
        $this->broadmailApiClient = $broadmailApiClient;

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
            'Shopware_CronJob_EpiProductExport' => 'onEpiProductExport',
            'Shopware_CronJob_EpiErrorHandler' => 'onEpiErrorHandler',
        ];
    }

    /**
     * Exports products using configured feeds
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function onEpiProductExport()
    {
        $this->getLogger()->info('CronJobSubscriber: onEpiProductExport called!');
        $this->registerComponents();

        $db = $this->getContainer()->get('db');
        $shops = $this->getContainer()->get('models')->getRepository(Shop::class)->findBy(array('active' => true));

        //$result = print_r($shops, false);
        /** @var \Shopware\Models\Shop\Shop $shop */
        $pluginName = $this->getPluginName();
        foreach ($shops as $shop) {
            // get shop specific config
            $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName($pluginName, $shop);
            $runExport = $config['runExport'];
            if ($runExport === true) {
                $exportUser = $config['sftpUsername'];
                $exportKey = $config['sftpPrivateKey'];
                $exportKeyPass = $config['sftpPassword'];
                $exportLink = $config['exportLink'];
                $exportFileName = $config['exportName'];

                $baseMessage = "CronJobsSubscriber::onEpiProductExport: ";
                if (empty($exportUser)) {
                    $this->getLogger()->error($baseMessage . 'missing export sftp username');
                    continue;
                }
                if (empty($exportKey)) {
                    $this->getLogger()->error($baseMessage . 'missing keyfile');
                    continue;
                }
                if (empty($exportLink)) {
                    $this->getLogger()->error($baseMessage . 'missing export link');
                    continue;
                }
                if (!preg_match('/feedID=([0-9]+)/', $exportLink, $matches)) {
                    $this->getLogger()->error($baseMessage . 'invalid export link, feedID param not found');
                    continue;
                }
                $feedId = $matches[1];
                if (!($generatedProductFeedPath = $this->generateProductFeed($feedId))) {
                    $this->getLogger()->error($baseMessage . 'error generating product feed',
                        array($feedId));
                    continue;
                }
                if (0 === filesize($generatedProductFeedPath)) {
                    $this->getLogger()->error($baseMessage . 'feed export file empty',
                        array($generatedProductFeedPath));
                    continue;
                }
                if (!($feedHandle = fopen($generatedProductFeedPath, 'r'))) {
                    $this->getLogger()->error($baseMessage . 'error opening export feed file',
                        array($generatedProductFeedPath));
                    continue;
                }
                if (empty($exportFileName)) {
                    $this->getLogger()->error($baseMessage . 'missing export file name');
                    continue;
                }
                $rsa = new RSA();
                if (!empty($exportKeyPass)) {
                    $rsa->setPassword($exportKeyPass);
                }
                $rsa->loadKey($exportKey);
                $sftp = new SFTP('transfer.campaign.optimizely.com');
                
                if (!$sftp->login($exportUser, $rsa)) {
                    $this->getLogger()->error($baseMessage . 'login error', $sftp->getErrors());
                    continue;
                } else {
                    $this->getLogger()->info('Logged in successfully to transfer.campaign.optimizely.com with user ' . $exportUser);
                }
                if (!$sftp->put($exportFileName, $feedHandle)) {
                    $this->getLogger()->error($baseMessage . 'error uploading file to server', $sftp->getErrors());
                } else {
                    $this->getLogger()->info('Successfully transferred file ' . $exportFileName);
                }
            }
        }
    }

    /**
     * Helper method to register components needed in the Product export cron's scope
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    private function registerComponents()
    {
        // Register phpseclib namespace
        $this->getContainer()->get('loader')->registerNamespace(
            'phpseclib',
            $this->getPluginDir() . '/Library/phpseclib2.0.4/phpseclib/'
        );
    }

    /**
     * @param int $feedId
     * @return string
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    private function generateProductFeed($feedId)
    {
        /** @var \Shopware\Models\ProductFeed\ProductFeed $feed */
        $feed = $this->getContainer()->get('models')->getRepository(ProductFeed::class)->find($feedId);
        if (null === $feed) {
            $this->getLogger()->error('CronJobsSubscriber::generateProductFeed: invalid feedID');

            return null;
        }

        /** @var \sExport $export */
        $export = $this->getContainer()->get('modules')->Export();
        $export->sSYSTEM = $this->getContainer()->get('system');
        $export->sFeedID = $feed->getId();
        $export->sHash = $feed->getHash();
        $export->sInitSettings();
        $export->sSmarty = clone $this->getContainer()->get('template');
        $export->sInitSmarty();
        $tmpfname = tempnam(sys_get_temp_dir(), $feed->getHash());
        $handleResource = fopen($tmpfname, 'wb');
        $export->executeExport($handleResource);

        return $tmpfname;
    }

    /**
     * @return bool
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \Enlight_Exception
     */
    public function onEpiErrorHandler()
    {
        $this->getLogger()->debug("CronJobSubscriber: onEpiErrorHandler called!");

        $maxRetryCount = (int)$this->getContainer()->getParameter('optivo_broadmail.max_retry_count');

        /** @var Repository $errorQueueRepository */
        $errorQueueRepository = $this->getContainer()->get('models')->getRepository(ErrorQueue::class);
        /** @var ErrorQueue[] $jobsToRetry */
        $jobsToRetry = $errorQueueRepository->getRetryableListQuery($maxRetryCount)->execute();

        /** @var ErrorQueue $job */
        foreach ($jobsToRetry as $job) {
            $this->broadmailApiClient->retryJob($job);

            // Send email if job is still on error status and maxRetryCount was reached
            if (
                $job->getErrorStatus() !== ErrorQueue::SUCCESS_STATUS &&
                $job->getRetryCount() >= $maxRetryCount
            ) {
                /** @var Shopware_Components_TemplateMail $templatemail */
                $templatemail = $this->getContainer()->get('templatemail');
                $emailToName = $this->getContainer()->getParameter('optivo_broadmail.error_email_to_name');
                $emailAddress = $this->getContainer()->getParameter('optivo_broadmail.error_email_address');
                $emailSubject = $this->getContainer()->getParameter('optivo_broadmail.error_email_subject');

                $mail = $templatemail->createMail(
                    OptivoBroadmail::OPTIVO_ERROR_REPORT_MAILMODEL,
                    array(
                        'subject' => $emailSubject,
                        'errorStatus' => $job->getErrorStatus(),
                        'errorMessage' => $job->getErrorMessage()
                    )
                );
                $mail->addTo($emailAddress, $emailToName);
                $mail->send();
            }
        }
        return true;
    }
}
