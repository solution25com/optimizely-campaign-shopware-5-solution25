<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Proxy;

use Psr\Log\LoggerInterface;

/**
 * TemplateMailProxy is a decorator for the Shopware TemplateMail component.
 * Some code duplication is necessary, since createMail has to be modified.
 */
class TemplateMailProxy extends \Shopware_Components_TemplateMail
{
    private $templateMail;

    /**
     * createMail
     * replaces default createMail method to add a different behaviour
     * for mails that should be sent with the Broadmail API
     *
     * @param string|\Shopware\Models\Mail\Mail $mailModel
     * @param array $context
     * @param \Shopware\Models\Shop\Shop $shop
     * @param array $overrideConfig*
     * @throws \Enlight_Exception
     *
     * @return \Enlight_Components_Mail
     */
    public function createMail($mailModel, $context = array(), $shop = null, $overrideConfig = array())
    {   // Hooked start
        $logger = Shopware()->Container()->get('optivo_broadmail.logger');

        if (null !== $shop) {
            $this->setShop($shop);
        }

        if (!($mailModel instanceof \Shopware\Models\Mail\Mail)) {
            $modelName = $mailModel;
            /* @var $mailModel \Shopware\Models\Mail\Mail */
            $mailModel = $this->getModelManager()->getRepository('Shopware\Models\Mail\Mail')->findOneBy(
                array('name' => $modelName)
            );
            if (!$mailModel) {
                throw new \Enlight_Exception("Mail-Template with name '{$modelName}' could not be found.");
            }
        }

        $config = Shopware()->Config();
        $inheritance = Shopware()->Container()->get('theme_inheritance');
        $eventManager = Shopware()->Container()->get('events');
        
        if ($this->getShop() !== null) {
            $defaultContext = [
                'sConfig'  => $config,
                'sShop'    => $config->get('shopName'),
                'sShopURL' => ($this->getShop()->getSecure() ? 'https://' : 'http://') . $this->getShop()->getHost() . $this->getShop()->getBaseUrl(),
            ];

            // Add theme to the context if given shop (or its main shop) has a template.
            $theme = null;
            if ($this->getShop()->getTemplate()) {
                $theme = $inheritance->buildConfig($this->getShop()->getTemplate(), $this->getShop(), false);
            } elseif ($this->getShop()->getMain() && $this->getShop()->getMain()->getTemplate()) {
                $theme = $inheritance->buildConfig($this->getShop()->getMain()->getTemplate(), $this->getShop(), false);
            }

            if ($theme) {
                $keys = $eventManager->filter(
                    'TemplateMail_CreateMail_Available_Theme_Config',
                    $this->themeVariables,
                    ['theme' => $theme]
                );

                $theme = array_intersect_key($theme, $keys);
                $defaultContext['theme'] = $theme;
            }
            
            $isoCode = $this->getShop()->getId();
            $translationReader = $this->getTranslationReader();

            if ($fallback = $this->getShop()->getFallback()) {
                $logger->debug('We read translations with fallback: ' . $isoCode . ' / ' . $fallback->getId() . ' / ' . $mailModel->getId());
                $translation = $translationReader->readWithFallback($isoCode, $fallback->getId(), 'config_mails', $mailModel->getId());
            } else {
                // We read translations without fallback:  / 2 []
                $logger->debug('We read translations without fallback: ' . $isoCode . ' / ' . $mailModel->getId());
                $translation = $translationReader->read($isoCode, 'config_mails', $mailModel->getId());
            }
            
            $mailModel->setTranslation($translation);
        
        } else {
            $defaultContext = array(
                'sConfig' => $config,
            );
        }

        $context = $eventManager->filter(
            'TemplateMail_CreateMail_MailContext',
            $context,
            [
                'mailModel' => $mailModel,
            ]
        );

        $mailContext = json_decode(json_encode($context),true);
        $mailModel->setContext($mailContext);
        $this->getModelManager()->flush($mailModel);
        $logger->debug("1. TemplateMailProxy - name of mailModel is: " . $mailModel->getName());
        // Hook end

        if ( !empty($mailModel) && 
             ( $this->_isOptivoMail($mailModel)
               || $mailModel->getName() === 'sOPTINNEWSLETTER'
               || $mailModel->getName() === 'sNEWSLETTERCONFIRMATION' )
           ) {
            $mailProxy = new MailProxy();
            if ($this->getShop() !== null) {
                $mailProxy->setShop($this->getShop()->get('isocode'));
            } else {
                $mailProxy->setShop("");
            }
            $logger->debug("2. TemplateMailProxy - name of mailModel is: " . $mailModel->getName());
            if ($mailModel->getName() === 'sOPTINNEWSLETTER') {
                $data = array(
                    'shop' => $this->getShop()->getName(),
                    'optin-type' => 'double',
                );
                $mailProxy->setOptivoData($data);
                $mailProxy->setContext(array_merge($context, $defaultContext));
            } else if ($mailModel->getName() === 'sNEWSLETTERCONFIRMATION' ) {
                $data = array(
                    'shop' => $this->getShop()->getName(),
                    'optin-type' => 'confirmed',
                );
                $mailProxy->setOptivoData($data);
                $mailProxy->setContext(array_merge($context, $defaultContext));
            } else {
                $data = $this->_parseOptivoData($mailModel, array_merge($context, $defaultContext), $translation);
                $mailProxy->setOptivoData($data);
                $mailProxy->setContext(array_merge($context, $defaultContext));
            }
            $mailProxy->setTemplateName($mailModel->getName());
            return $mailProxy;
        // Fallback to Shopware default behaviour when its not a Broadmail managed mail
        } else {
            return parent::createMail($mailModel, $context, $shop, $overrideConfig);
        }
    }

    /**
     * _isOptivoMail
     * check if the mail should be send with the Broadmail API
     * @param  \Shopware\Models\Mail\Mail $mail the MailModel
     * @return boolean
     */
    protected function _isOptivoMail($mail)
    {
        $attr = $mail->getAttribute();
        if (!empty($attr) && $attr->getOptivoEnable() === 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * _parseOptivoData
     * @param  \Shopware\Models\Mail\Mail $mailModel the MailModel
     * @param  array $context the MailModel
     * @return array
     */
    protected function _parseOptivoData($mailModel, $context, $translation)
    {
        $mailAttr = $mailModel->getAttribute();
        
        if ($translation['OptivoAuthcode']) {
            $authCode = $translation['OptivoAuthcode'];
        } else {
            $authCode = $mailAttr->getOptivoAuthCode();
        }
        
        if ($translation['OptivoBmMailId']) {
            $bmMailId = $translation['OptivoBmMailId'];
        } else {
            $bmMailId = $mailAttr->getOptivoBmMailId();
        }

        if ($translation['Optivo-Content']) {
            $templateString = $translation['Optivo-Content'];
        } else {
            $templateString = $mailAttr->getOptivoContent();
        }

        $template = $this->getStringCompiler()->compileString($templateString, $context);

        $data = $this->_parseOptivoTemplate($template);
        $data['authCode'] = $authCode;
        $data['bmMailingId'] = $bmMailId;
        $pluginName = Shopware()->Container()->getParameter('optivo_broadmail.plugin_name');
        $_bmOptinId = Shopware()->Config()->getByNamespace($pluginName, 'optivoOptInId');
        if (!empty($_bmOptinId)) {
            $data['bmOptInId'] = $_bmOptinId;
        }

        if (!empty($this->shop) && ($this->shop instanceof Shopware\Models\Shop\Shop)) {
            $data['shop'] = $this->shop->getName();
        }

        return $data;
    }

    /**
     * _parseOptivoTemplate
     * @param  string $template the the optivo template
     * @return array
     */
    protected function _parseOptivoTemplate($template)
    {

        $data = explode(';' . PHP_EOL, $template);
        $params = array();

        foreach ($data as $row) {
            $param = explode('=', $row);
            if (!empty($param) && count($param) === 2) {
                $params[str_replace(PHP_EOL, '', $param[0])] = $param[1];
            }
        }

        return $params;
    }
}
