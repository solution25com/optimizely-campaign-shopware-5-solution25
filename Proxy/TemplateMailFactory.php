<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */
namespace OptivoBroadmail\Proxy;

use Shopware\Components\DependencyInjection\Container;

/**
 * This is a rather simple decorator for the TemplateMailFactory. Main Goal is
 * to genereate instances OptivoBroadmail\Proxy\TemplateMailProxy instead of
 * Shopware TemplateMailProxy.
 *
 * @category  Episerver GmbH
 * @copyright Copyright (c) Episerver GmbH
 */
class TemplateMailFactory
{
    /**
     * @param Container $container
     *
     * @return TemplateMailProxy
     */
    public function factory(Container $container)
    {
        $container->load('mailtransport');

        $mailer = new TemplateMailProxy();

        if ($container->initialized('shop')) {
            $mailer->setShop($container->get('shop'));
        }

        $modelManager = null;
        // if statements to keep compatibility with both 5.6 and 5.7
        if ($container->has('Models')) {
            $modelManager = $container->get('Models');
        } elseif ($container->has(\Shopware\Components\Model\ModelManager::class)) {
            $modelManager = $container->get(\Shopware\Components\Model\ModelManager::class);
        }

        if ($modelManager !== null) {
            $mailer->setModelManager($modelManager);
        }

        $template = null;
        $stringCompiler = null;
        // if statements to keep compatibility with both 5.6 and 5.7
        if ($container->has('Template')) {
            $template = $container->get('Template');
        } elseif ($container->has(\Enlight_Template_Manager::class)) {
            $template = $container->get(\Enlight_Template_Manager::class);
        }

        if ($template !== null) {
            $stringCompiler = new \Shopware_Components_StringCompiler($template);
        }

        if ($stringCompiler !== null) {
            $mailer->setStringCompiler($stringCompiler);
        }

        return $mailer;
    }
}
