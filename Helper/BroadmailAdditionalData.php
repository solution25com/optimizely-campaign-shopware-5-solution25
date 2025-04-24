<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Helper;

/**
 * Helper class for extracting user defined form data.
 */
class BroadmailAdditionalData
{
    const ADDITIONAL_FIELD_PREFIX = 'bm_';

    public function addAdditionalData($additionalData, $result)
    {
        if (!empty($additionalData) && is_array($additionalData)) {
            foreach ($additionalData as $key => $oneField) {
                if (strpos($key, self::ADDITIONAL_FIELD_PREFIX) === 0) {
                    $newKey = $this->getKeyForAdditionalData($key, self::ADDITIONAL_FIELD_PREFIX);
                    $result[$newKey] = $oneField;
                }
            }
        }

        return $result;
    }

    private function getKeyForAdditionalData($key, $prefix)
    {
        return substr($key, strlen($prefix));
    }
}
