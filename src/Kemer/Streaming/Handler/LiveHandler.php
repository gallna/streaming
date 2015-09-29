<?php
namespace Kemer\Streaming\Handler;

use Zend\Http\Response;
use Kemer\Stream\StreamInterface;

class LiveHandler extends AbstractHandler
{
    /**
     * Handler constructor
     *
     * @param StreamInterface $stream
     */
    public function __construct(StreamInterface $stream)
    {
        $this->setStream($stream);
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
