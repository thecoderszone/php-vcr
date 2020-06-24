<?php

namespace VCR;

use VCR\Storage\Storage;
use VCR\Util\Assertion;

/**
 * A Cassette records and plays back pairs of Requests and Responses in a Storage.
 */
class Cassette
{
    /**
     * Casette name
     *
     * @var string
     */
    protected $name;

    /**
     * VCR configuration.
     *
     * @var Configuration
     */
    protected $config;

    /**
     * Storage used to store records and request pairs.
     *
     * @var Storage
     */
    protected $storage;

    /**
     * Indicates whether the cassette has started playback.
     *
     * @var boolean
     */
    protected $startedPlayback = false;
    
    /**
     * Creates a new cassette.
     *
     * @param  string           $name    Name of the cassette.
     * @param  Configuration    $config  Configuration to use for this cassette.
     * @param  Storage          $storage Storage to use for requests and responses.
     * @throws \VCR\VCRException If cassette name is in an invalid format.
     */
    public function __construct($name, Configuration $config, Storage $storage)
    {
        Assertion::string($name, 'Cassette name must be a string, ' . \gettype($name) . ' given.');

        $this->name = $name;
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * Returns true if a response was recorded for specified request.
     *
     * @return boolean True if a response was recorded for specified request.
     */
    public function hasResponse()
    {
        return $this->playback() !== null;
    }

    /**
     * Returns a response for given request or null if not found.
     *
     * @return Response|null Response for specified request.
     */
    public function playback()
    {
        if ( ! $this->startedPlayback) {
            $this->storage->rewind();
            $this->startedPlayback = true;
        }
        
        if ($this->storage->valid()) {
            $recording = $this->storage->current();
            $response  = Response::fromArray(isset($recording['response']) ? $recording['response'] : $recording);
        }
        
        $this->storage->next();
        
        return $response ?? null;
    }

    /**
     * Records a request and response pair.
     *
     * @param Response $response Response to record.
     *
     * @return void
     */
    public function record(Response $response)
    {
        $this->storage->storeRecording($response->toArray());
    }

    /**
     * Returns the name of the current cassette.
     *
     * @return string Current cassette name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns true if the cassette was created recently.
     *
     * @return boolean
     */
    public function isNew()
    {
        return $this->storage->isNew();
    }
}
