<?php
namespace Kemer\Streaming\Socket;

use Zend\Http\Response;
use Kemer\Stream\Stream;
use Kemer\Stream\Exceptions\SocketException;
use Kemer\Stream\Exceptions\ProcessException;
use Psr\Http\Message\StreamInterface;

class LiveServer extends AbstractSocketServer
{
    /**
     * Server constructor
     *
     * @param string $url
     */
    public function __construct(StreamInterface $stream, $host, $port)
    {
        $this->setStream($stream);
        $this->setAddress($host, $port);
    }

    /**
     * Sends a chunk response to new clients to initiate connection
     *
     * @param resource $client
     */
    public function openConnection($client)
    {
        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_200);
        $response->getHeaders()->addHeaders([
            'Content-Type' => "video/mp4",
            //'Content-Type' => $this->getContentType(),
            "Cache-Control" => "no-cache",
        ]);
        e($response->toString(), 'blue');
        fwrite($client, $response->toString());
    }

    /**
     * Sends a final chunk response to client and finish connection
     *
     * @param resource $client
     */
    public function closeConnection($client)
    {

    }
}
