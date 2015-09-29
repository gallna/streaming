<?php
namespace Kemer\Streaming\Handler;

use Kemer\Streaming\PseudoStream\ChunkedStream;
use Kemer\Stream\StreamInterface;
use Zend\Http\Response;

class ChunkedHandler extends AbstractHandler
{
    /**
     * Handler constructor
     *
     * @param StreamInterface $stream
     */
    public function __construct(StreamInterface $stream)
    {
        parent::__construct(new ChunkedStream($stream));
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
        // $client->write($response->toString());
    }

    /**
     * Sends a final chunk response to client and finish connection
     *
     * @param resource $client
     */
    public function closeConnection($client)
    {
        $this->write([$client], "0\r\n\r\n");
        // $client->write("0\r\n\r\n");
    }
}
