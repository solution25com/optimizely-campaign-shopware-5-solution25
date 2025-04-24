<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Models\ErrorQueue;

use Shopware\Components\Model\ModelRepository;

class Repository extends ModelRepository
{
    public function getRetryableListQuery($maxRetryCount)
    {
        $builder = $this->createQueryBuilder('error_queue');
        $builder
            ->where('error_queue.errorStatus != :status')
            ->andWhere('error_queue.retryCount < :maxRetryCount')
            ->setParameter('status', ErrorQueue::SUCCESS_STATUS)
            ->setParameter('maxRetryCount', $maxRetryCount);

        return $builder->getQuery();
    }

}
