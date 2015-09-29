<?php
namespace Kemer\Stream\Threaded;

use Zend\Http\Response;
use Kemer\Stream\Stream;
use Kemer\Stream\Exceptions\SocketException;
use Psr\Http\Message\StreamInterface;

class StreamServer extends \Thread
{
    /**
     * @var string
     */
    private $port;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $command;

    /**
     * @var resource
     */
    private $server;

    /**
     * @var array
     */
    private $clients = [];

    /**
     * @var integer
     */
    private $buffer = 32768;

    /**
     * Server constructor
     *
     * @param string $url
     */
    public function __construct($url, $port, $host = "127.0.0.1")
    {
        $this->command = sprintf(
            'ffmpeg -i "%s" -c copy -bsf:v h264_mp4toannexb -f mpegts pipe:',
            $url
        );
        $this->port = $port;
        $this->host = $host;
    }

    /**
     * Run server
     *
     * @param integer $port
     * @param string $host
     * @return void
     */
    public function run()
    {
        include "/data/www/stream/stream.feed/vendor/autoload.php";
        $pipeStream = new Stream\ReadPipeStream($this->command);
        $chunkedStream = new Stream\ChunkedStream($pipeStream);

        $this->startServer($this->port, $this->host);
        while(true) {
            $this->loop($chunkedStream);
        }
    }

    /**
     * Create a TCP socket
     *
     * @param integer $port
     * @param string $host
     * @return resource
     */
    private function startServer($port, $host)
    {
        $tcp = sprintf("tcp://%s:%s", $host, $port);
        if (($this->server = stream_socket_server($tcp, $errno, $errstr)) === false) {
            throw new \Exception("($errno) $errstr");
        }
        return $this->server;
    }

    /**
     * Loop over existing connections
     *
     * @param StreamInterface $stream
     * @return void
     */
    private function loop(StreamInterface $stream)
    {
        $read = $this->clients;
        $read[] = $this->server;
        $write = $this->clients;

        if (stream_select($read, $write, $except, 0) > 0) {

            //new client
            if (in_array($this->server, $read)) {
                if ($client = stream_socket_accept($this->server)) {
                    $this->addClient($client);
                }
                //delete the server socket from the read sockets
                unset($read[array_search($this->server, $read)]);
            }
            if (!empty($read)) {
                $this->read($read);
            }

            if (!empty($write)) {
                $chunk = $stream->read($this->buffer);
                $this->write($write, $chunk);
            }
        }
    }

    /**
     * Adds new client
     *
     * @param resource $client
     */
    private function addClient($client)
    {
        stream_set_blocking($client, 0);
        $this->openConnection($client);
        $this->clients[] = $client;
        e("New connection: ".stream_socket_get_name($client, true)." (".count($this->clients).")", 'light_cyan');
    }

    /**
     * Sends a chunk response to new clients to initiate connection
     *
     * @param resource $client
     */
    private function openConnection($client)
    {
        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_200);
        $response->getHeaders()->addHeaders([
            'Content-Type' => "video/mp4",
            //'Accept-Ranges' => 'bytes',
            'Transfer-Encoding' => 'chunked',
        ]);
        e($response->toString(), 'blue');
        fwrite($client, $response->toString());
    }

    /**
     * Remove client
     *
     * @param resource $client
     */
    private function removeClient($client)
    {
        if (is_resource($client)) {
            e("A client disconnected: ".stream_socket_get_name($client, true)." (".count($this->clients).")", 'red');
            unset($this->clients[array_search($client, $this->clients)]);
            @fclose($client);
        } else {
            e("A client already disconnected ? (".count($this->clients).")", 'red');
        }

    }

    /**
     * Read client messages
     *
     * @param resource[] $clients
     */
    private function read(array $clients)
    {
        foreach($clients as $sock) {
            if(!($data = stream_get_contents($sock))) {
                $this->removeClient($sock);
                continue;
            }
            e($data, 'green');
        }
    }

    /**
     * Write chunk to each clients
     *
     * @param resource[] $clients
     * @param string $chunk
     */
    private function write(array $clients, $chunk)
    {
        foreach($clients as $sock) {
            try {
                fwrite($sock, $chunk);
            } catch (\ErrorException $e) {
                $exception = new SocketException();
                if (!$exception->isConnectionClosed()) {
                    throw $exception;
                }
                $this->removeClient($sock);
            }
        }
    }
}
