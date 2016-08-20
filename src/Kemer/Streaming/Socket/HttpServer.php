<?php
namespace Kemer\Streaming\Socket;

use GuzzleHttp\Psr7;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/*
$server = new Kemer\Streaming\Socket\HttpServer(new class {
    public function handle($request, $response)
    {
        return $response->withBody(Psr7\stream_for("Hello browser"));
    }
});
$server->run(8088, "0.0.0.0");
 */

class HttpServer extends AbstractSocketServer
{
    /**
     * @var handler
     */
    private $handler;

    /**
     * @var messages[]
     */
    private $messages = [];

    /**
     * Message body chunk size
     * 4096/8192/16384/32768
     * @var integer
     */
    private $chunkSize;

    /**
     * Server constructor
     *
     * @param string $handler
     */
    public function __construct($handler, $chunkSize = 8192)
    {
        $this->handler = $handler;
        $this->chunkSize = (int)$chunkSize;
    }

    /**
     * Adds new client
     *
     * @param resource $client
     */
    protected function clientAdded($client)
    {

    }

    /**
     * Remove client
     *
     * @param resource $client
     */
    protected function clientRemoved($client)
    {
        if (isset($this->messages[(int)$client])) {
            unset($this->messages[(int)$client]);
        }

    }

    /**
     * Read client content
     *
     * @param resource $client
     */
    protected function read($client)
    {
        if($message = stream_get_contents($client)) {
            $response = $this->handler->handle(
                Psr7\parse_request($message),
                new Psr7\Response()
            );
            if (is_string($response)) {
                $response = Psr7\parse_response($response);
            }
            return $this->messages[(int)$client] = $response;
        }
        $this->removeClient($client);
    }

    /**
     * Write response to the client
     *
     * @param resource $client
     */
    protected function write($client)
    {
        if (isset($this->messages[(int)$client])) {
            if (($response = $this->messages[(int)$client]) instanceof StreamInterface) {
                return $this->chunk($client);
            }

            // If message is too big - split it to chunks
            $response = $response->withoutHeader(
                "Content-Length"
            );
            $contentLength = $response->getBody()->getSize();
            if ($contentLength && ($this->chunkSize * 4) > $contentLength) {
                fwrite($client, Psr7\str($response));
                return $this->removeClient($client);
            }

            // Split and send head now and store body to send it next time
            list($head, $body) = $this->split($response);
            $this->messages[(int)$client] = $body;
            fwrite($client, $head);
        }
    }

    /**
     * Write chunk to the client
     *
     * @param resource $client
     */
    private function chunk($client)
    {
        $body = $this->messages[(int)$client];
        if (!$body->eof()) {
            return fwrite($client, $body->read($this->chunkSize));
        }
        $this->removeClient($client);
    }

    /**
     * Returns the string representation of an HTTP message split to headers and body.
     *
     * @param ResponseInterface $message Message to convert to a string.
     *
     * @return string
     */
    private function split(ResponseInterface $response)
    {
        $head = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        foreach ($response->getHeaders() as $name => $values) {
            $head .= "\r\n{$name}: " . implode(', ', $values);
        }
        return ["{$head}\r\n\r\n", $response->getBody()];
    }
}
