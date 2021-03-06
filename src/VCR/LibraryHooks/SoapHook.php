<?php

namespace VCR\LibraryHooks;

use VCR\Util\Assertion;
use VCR\VCRException;
use VCR\Request;
use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Util\StreamProcessor;

/**
 * Library hook for curl functions.
 */
class SoapHook implements LibraryHook
{
    /**
     * @var callable|null
     */
    private static $requestCallback;

    /**
     * @var string
     */
    private $status = self::DISABLED;

    /**
     * @var AbstractCodeTransform
     */
    private $codeTransformer;

    /**
     * @var \VCR\Util\StreamProcessor
     */
    private $processor;

    /**
     * Creates a SOAP hook instance.
     *
     * @param AbstractCodeTransform  $codeTransformer
     * @param StreamProcessor $processor
     *
     * @throws \BadMethodCallException in case the Soap extension is not installed.
     */
    public function __construct(AbstractCodeTransform $codeTransformer, StreamProcessor $processor)
    {
        if (!class_exists('\SoapClient')) {
            throw new \BadMethodCallException('For soap support you need to install the soap extension.');
        }

        if (!class_exists('\DOMDocument')) {
            throw new \BadMethodCallException('For soap support you need to install the xml extension.');
        }

        $this->processor = $processor;
        $this->codeTransformer = $codeTransformer;
    }

    /**
     * @param string $request
     * @param string $location
     * @param string $action
     * @param integer $version
     * @param int $one_way
     * @param array<string,mixed> $options
     *
     * @return string SOAP response.
     */
    public function doRequest(string $request, string $location, string $action, int $version, int $one_way = 0, array $options = array()): string
    {
        if ($this->status === self::DISABLED) {
            throw new VCRException('Hook must be enabled.', VCRException::LIBRARY_HOOK_DISABLED);
        }

        $vcrRequest = new Request('POST', $location);

        if ($version === SOAP_1_1) {
            $vcrRequest->setHeader('Content-Type', 'text/xml; charset=utf-8;');
            $vcrRequest->setHeader('SOAPAction', $action);
        } else { // >= SOAP_1_2
            $vcrRequest->setHeader(
                'Content-Type',
                sprintf('application/soap+xml; charset=utf-8; action="%s"', $action)
            );
        }

        $vcrRequest->setBody($request);

        if (!empty($options['login'])) {
            $vcrRequest->setAuthorization($options['login'], $options['password']);
        }

        /* @var \VCR\Response $response */
        $requestCallback = self::$requestCallback;
        Assertion::isCallable($requestCallback);
        $response = $requestCallback($vcrRequest);

        return $response->getBody();
    }

    /**
     * @inheritDoc
     */
    public function enable(\Closure $requestCallback): void
    {
        Assertion::isCallable($requestCallback, 'No valid callback for handling requests defined.');
        self::$requestCallback = $requestCallback;

        if ($this->status == self::ENABLED) {
            return;
        }

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
        $this->processor->intercept();

        $this->status = self::ENABLED;
    }

    /**
     * @inheritDoc
     */
    public function disable(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        self::$requestCallback = null;

        $this->status = self::DISABLED;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(): bool
    {
        return $this->status == self::ENABLED;
    }

    /**
     * Cleanup.
     *
     * @return  void
     */
    public function __destruct()
    {
        self::$requestCallback = null;
    }
}
