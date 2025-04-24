<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Subscriber;

use Enlight\Event\SubscriberInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BaseSubscriber implements SubscriberInterface
{
    /** @var ContainerInterface */
    protected $container;

    /** @var LoggerInterface */
    protected $logger;

    /** @var string */
    protected $pluginDir;

    /** @var string */
    protected $pluginName;

    /** @var  array */
    protected $pluginMetadata;

    public function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->logger = $logger;

        // Init plugin info
        $this->pluginDir = $this->container->getParameter('optivo_broadmail.plugin_dir');
        $this->pluginName = $this->container->getParameter('optivo_broadmail.plugin_name');
        $this->pluginMetadata = $this->container->getParameter('optivo_broadmail.plugin_metadata');
    }

    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        return $this->container;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return string
     */
    protected function getPluginDir()
    {
        return $this->pluginDir;
    }

    /**
     * @return string
     */
    protected function getPluginName()
    {
        return $this->pluginName;
    }

    /**
     * @return array
     */
    protected function getPluginMetadata()
    {
        return $this->pluginMetadata;
    }
}
