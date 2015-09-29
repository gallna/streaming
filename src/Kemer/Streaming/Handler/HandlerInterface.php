<?php
namespace Kemer\Streaming\Handler;

use Kemer\Stream\StreamInterface;

interface HandlerInterface
{

    /**
     * Set stream to serve
     *
     * @param StreamInterface $stream
     * @return this
     */
    public function setStream(StreamInterface $stream);

    /**
     * Returns stream
     *
     * @return StreamInterface
     */
    public function getStream();

    /**
     * Handle connection
     *
     * @param StreamInterface $connection
     * @return void
     */
    public function handle(StreamInterface $client);

    /**
     * Sends a chunk response to new clients to initiate connection
     *
     * @param resource $client
     */
    public function openConnection($client);

    /**
     * Sends a final chunk response to client and finish connection
     *
     * @param resource $client
     */
    public function closeConnection($client);

    /**
     * Close server
     *
     * @return void
     */
    public function close();
}
