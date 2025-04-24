<?php
/**
 * Episerver Campaign
 *
 * @version   1.1.5
 * @license   https://www.episerver.de/agb
 * @copyright Copyright (c) 2018 Episerver GmbH (http://www.episerver.de/)
 * All rights reserved.
 */

namespace OptivoBroadmail\Components;

use OptivoBroadmail\Models\ErrorQueue\ErrorQueue;
use Psr\Log\LoggerInterface;
use Shopware\Components\Model\ModelManager;

/**
 * BroadmailAPI
 * all necessary API calls are implemented in this class
 *
 * @category  Optivo
 * @package   Optivo_Broadmail
 * @author    optivo GmbH <shoptivo@optivo.de>
 */
class BroadmailAPI
{
    const API_ERROR_WRONG_TAG = 'wrong_tag';
    const API_ERROR_DUPLICATE = 'duplicate';
    const API_ERROR_SYNTAX_ERROR = 'syntax_error';
    const API_ERROR_BLACKLISTED = 'blacklisted';
    const API_ERROR_TOO_MANY_BOUNCES = 'too_many_bounces';
    const API_ERROR_SYSTEM_ERROR = 'system_error';
    const API_ERROR_UNSUBSCRIBED = 'unsubscribed';


    /**
     * Broadmail http API
     * @access protected
     */
    protected $baseURL;

    /**
     * curl request timeout
     * @access protected
     */
    protected $timeout;

    /**
     * headers
     * @access protected
     */
    protected $header;

    /** @var  LoggerInterface */
    protected $logger;

    /** @var  ModelManager */
    protected $modelManager;


    /**
     * constructor
     * @param LoggerInterface $logger
     * @param ModelManager $modelManager
     * @param string $baseURL
     * @param int $timeout
     * @param array $header
     */
    public function __construct(
        LoggerInterface $logger,
        ModelManager $modelManager,
        $baseURL = 'https://api.broadmail.de/http',
        $timeout = 60,
        $header = array(
            'Content-Type: application/x-www-form-urlencoded'
        )
    ) {
        $this->logger = $logger;
        $this->modelManager = $modelManager;
        $this->baseURL = $baseURL;
        $this->timeout = $timeout;
        $this->header = $header;
    }


    /**
     * subscribe
     * this methods subscribes a user to broadmail
     *
     * @param string $email email of user to subscribe
     * @param array $data additional fields
     *
     * @return array status of api call
     */
    public function subscribe($email, $data)
    {
        $data['bmRecipientId'] = $email;

        return $this->call('form', 'subscribe', $data);
    }


    /**
     * unsubscribe
     * this method unsubscribes a user from broadmail
     *
     * @param string $email email of user to unsubscribe
     *
     * @return array status of api call
     */
    public function unsubscribe($email, $data = array())
    {
        $data['bmRecipientId'] = $email;

        return $this->call('form', 'unsubscribe', $data);
    }


    /**
     * sendtransactionmail
     * this methods subscribes a user to broadmail
     *
     * @param string $email email of user to subscribe
     * @param array $data additional fields
     *
     * @return array status of api call
     */
    public function sendtransactionmail($email, $data)
    {
        $data['bmRecipientId'] = $email;
        return $this->call('form', 'sendtransactionmail', $data);
    }


    /**
     * update customer fields. Overwriting existing data is the default on
     * on using the API.
     *
     * @param string $email email of user to subscribe
     * @param array $data customer fields
     * @param bool $overwrite
     *
     * @return array status of api call
     */
    public function updateFields($email, $data, $overwrite = true)
    {
        $data['bmRecipientId'] = $email;
        if ($overwrite) {
            $data['bmOverwrite'] = true;
        }
        return $this->call('form', 'updatefields', $data);
    }

    /**
     * @param ErrorQueue $job
     *
     * @return array status of api call
     */
    public function retryJob(ErrorQueue $job)
    {
        $options = $job->getOptions();
        return $this->call($options['endpoint'], $options['method'], $options['data'], $job);
    }


    /**
     * call
     * this method calls the Broadmail-http-API via curl
     *
     * @param string $endpoint the api endpoint
     * @param string $method
     * @param array|string $data the data to send
     * @param ErrorQueue|null $retriedJob
     * @return array status of api call
     */
    protected function call($endpoint, $method, $data, ErrorQueue $retriedJob = null)
    {
        // validate API authCode
        if (empty($data['authCode'])) {
            $message = sprintf(
                'BroadmailAPI::call: API not configured, authorization code is missing for method "%s".',$method
            );
            $this->logger->warning($message);
            return ['success' => false, 'message' => $message];
        }

        $logData = $data; // for logging in case of error

        $authCode = $data['authCode'];
        unset($data['authCode']);

        $url = $this->baseURL . '/' . $endpoint . '/' . $authCode . '/' . $method;

        // Remove authentication code from URL for debug logging
        $logUrl = $this->baseURL . '/' . $endpoint . '/authCode/' . $method;
        $this->logger->debug(sprintf("Call URL: %s", $logUrl));

        $payload = http_build_query($data);

        $this->logger->debug('Payload:', explode('&', $payload));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10000);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'optivo® broadmail Shopware Integration');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = array(
            'response' => curl_exec($ch),
            'error' => curl_error($ch),
            'errno' => curl_errno($ch),
            'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)
        );
        curl_close($ch);

        $this->logger->debug(sprintf('Response: %s', $result['response']), $result);

        $responseStatus = $this->checkResponse($result);
        $isResponseSuccessful = ($responseStatus === ErrorQueue::SUCCESS_STATUS);

        $options = array(
            'endpoint' => $endpoint,
            'method' => $method,
            'data' => $logData
        );

        if ($retriedJob) { //update job
            $retriedJob->setLastRetryAt(new \DateTime('now'));
            $retriedJob->setRetryCount($retriedJob->getRetryCount() + 1);
            $retriedJob->setErrorStatus($responseStatus);
            $retriedJob->setErrorMessage($this->buildErrorMessage($responseStatus, $result));
            $retriedJob->setOptions($isResponseSuccessful ? array() : $options);
        } elseif (!$isResponseSuccessful) { //create a new job if error
            $retriedJob = new ErrorQueue();
            $retriedJob->setErrorStatus($responseStatus);
            $retriedJob->setErrorMessage($this->buildErrorMessage($responseStatus, $result));
            $retriedJob->setOptions($options);
            $this->modelManager->persist($retriedJob);
        }

        if ($retriedJob) {
            $this->modelManager->flush($retriedJob);
        }

        $status = array(
            'success' => $isResponseSuccessful,
            'message' => $isResponseSuccessful?$result['response']:$result['error'],
            'endpoint' => $endpoint
        );

        return $status;
    }

    /**
     * checks the response
     * @param array $result
     * @return string error status
     */
    private function checkResponse(array $result)
    {
        if ($result['errno'] !== 0) {
            return ErrorQueue::NETWORK_ERROR_STATUS;
        }

        if ($result['http_code'] !== 200) {
            return ErrorQueue::SERVER_ERROR_STATUS;
        }

        return ErrorQueue::SUCCESS_STATUS;
    }

    private function buildErrorMessage($status, array $result)
    {
        $errorMessage = '';
        if ($status === ErrorQueue::NETWORK_ERROR_STATUS) {
            $errorMessage = $result['error'] . '(errno=' . $result['errno'] . ')';
        } elseif ($status === ErrorQueue::SERVER_ERROR_STATUS) {
            $errorMessage = 'server replied: ' . $result['http_code'];
        }
        return $errorMessage;
    }

}
