<?php
namespace Kemer\Streaming\Socket;

use Zend\Http\Response;
use Kemer\Stream\Stream;
use Kemer\Stream\Exceptions\SocketException;
use Kemer\Stream\Exceptions\ProcessException;
use Psr\Http\Message\StreamInterface;

class ChunkedServer extends AbstractSocketServer
{
    /**
     * Server constructor
     *
     * @param string $url
     */
    public function __construct(StreamInterface $stream, $host, $port)
    {
        $this->setStream(new Stream\ChunkedStream($stream));
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
            'Accept-Ranges' => 'bytes',
            'Transfer-Encoding' => 'chunked',
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
        $this->write([$client], "0\r\n\r\n");
    }
}
