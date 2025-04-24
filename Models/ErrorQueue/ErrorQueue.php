<?php
/**
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Models\ErrorQueue;

use Shopware\Components\Model\ModelEntity, Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Repository")
 * @ORM\Table(name="s_optivo_broadmail_error_queue")
 */
class ErrorQueue extends ModelEntity
{
    const SUCCESS_STATUS = 'SUCCESS';
    const NETWORK_ERROR_STATUS = 'NETWORK_ERROR';
    const SERVER_ERROR_STATUS = 'SERVER_ERROR';

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     */
    private $createdAt;

    /**
     * @var integer
     *
     * @ORM\Column(name="retry_count", type="integer", nullable=false)
     */
    private $retryCount;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_retry_at", type="datetime", nullable=true)
     */
    private $lastRetryAt;

    /**
     * @var string
     *
     * @ORM\Column(name="error_status", type="string", nullable=false)
     */
    private $errorStatus;

    /**
     * @var string
     *
     * @ORM\Column(name="error_message", type="string", nullable=false)
     */
    private $errorMessage;

    /**
     * @var array
     *
     * @ORM\Column(name="options", type="array", nullable=false)
     */
    private $options;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->retryCount = 0;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     * @return ErrorQueue
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return int
     */
    public function getRetryCount()
    {
        return $this->retryCount;
    }

    /**
     * @param int $retryCount
     * @return ErrorQueue
     */
    public function setRetryCount($retryCount)
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getLastRetryAt()
    {
        return $this->lastRetryAt;
    }

    /**
     * @param \DateTime $lastRetryAt
     * @return ErrorQueue
     */
    public function setLastRetryAt($lastRetryAt)
    {
        $this->lastRetryAt = $lastRetryAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getErrorStatus()
    {
        return $this->errorStatus;
    }

    /**
     * @param string $errorStatus
     * @return ErrorQueue
     */
    public function setErrorStatus($errorStatus)
    {
        $this->errorStatus = $errorStatus;
        return $this;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @param string $errorMessage
     * @return ErrorQueue
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     * @return ErrorQueue
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }
}
