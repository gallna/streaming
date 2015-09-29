<?php
namespace Kemer\Streaming\Socket;

use Zend\Http\Response;
use Kemer\Stream\Stream;
use Kemer\Stream\Exceptions\SocketException;
use Kemer\Stream\Exceptions\ProcessException;
use Psr\Http\Message\StreamInterface;

interface SocketServerInterface
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
     * Set server host and port
     *
     * @param integer $port
     * @param string $host
     * @return this
     */
    public function setAddress($host, $port);

    /**
     * Run server
     *
     * @return void
     */
    public function run();

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
