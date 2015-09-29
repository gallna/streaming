<?php
namespace Kemer\Streaming\Socket;

use Zend\Http\Response;
use Kemer\Stream\Stream;
use Kemer\Stream\Exceptions\SocketException;
use Kemer\Stream\Exceptions\ProcessException;
use Psr\Http\Message\StreamInterface;

abstract class AbstractSocketServer implements SocketServerInterface
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var integer
     */
    private $port;

    /**
     * @var StreamInterface
     */
    private $stream;

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
    private $buffer = 16384;

    /**
     * @var bool
     */
    private $running = true;

    /**
     * @var Redis
     */
    private $redis;

    /**
     * Server constructor
     *
     * @param string $url
     */
    public function __construct($url, $port, $host = "127.0.0.1")
    {
        $this->command = sprintf(
            'ffmpeg -re -i "%s" -c copy -bsf:v h264_mp4toannexb -f mpegts pipe:',
            $url
        );
    }

    /**
     * Set stream to serve
     *
     * @param StreamInterface $stream
     * @return this
     */
    public function setStream(StreamInterface $stream)
    {
        $this->stream = $stream;
        return $this;
    }

    /**
     * Set server host and port
     *
     * @param StreamInterface $stream
     * @return this
     */
    public function setAddress($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
        return $this;
    }

    /**
     * Returns stream
     *
     * @return StreamInterface
     */
    public function getStream()
    {
        return $this->stream;
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
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);

        $this->startServer($this->port, $this->host);
        while($this->running) {
            $this->loop($this->stream);
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
    private $skipped = 0;
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
                try {
                    //$chunk = $stream->read($this->buffer);
                    $chunk = $stream->getContents();
                } catch (ProcessException $e) {
                    $this->close();
                    e($e->getMessage(), 'green');
                    exit($e->getCode());
                }
                $this->write($write, $chunk);
            }
        } elseif (empty($this->clients)) {
            //$chunk = $stream->read($this->buffer);
            $this->skipped++;
            e("skipped... ".$this->skipped, 'yellow');
            $chunk = $stream->getContents();
        } else {
            $this->skipped++;
            e("skipped... ".$this->skipped, 'red');
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
        $this->redis->publish('server-1', 'clients '.count($this->clients));
        e("New client: ".stream_socket_get_name($client, true)." (".count($this->clients).")", 'light_cyan');
    }

    /**
     * Remove client
     *
     * @param resource $client
     */
    private function removeClient($client)
    {
        if (is_resource($client)) {
            e("Client disconnected: ".stream_socket_get_name($client, true)." (".(count($this->clients) - 1).")", 'cyan');
            unset($this->clients[array_search($client, $this->clients)]);
            @fclose($client);
        } else {
            e("Client already disconnected ? (".count($this->clients).")", 'red');
        }
    }

    /**
     * Sends a chunk response to new clients to initiate connection
     *
     * @param resource $client
     */
    abstract public function openConnection($client);

    /**
     * Sends a final chunk response to client and finish connection
     *
     * @param resource $client
     */
    abstract public function closeConnection($client);


    /**
     * Read client messages
     *
     * @param resource[] $clients
     */
    protected function read(array $clients)
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
    protected function write(array $clients, $chunk)
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


    /**
     * Close server
     *
     * @return void
     */
    public function close()
    {
        foreach ($this->clients as &$client) {
            $this->closeConnection($client);
            $this->removeClient($client);
        }
        if (is_resource($this->server)) {
            fclose($this->server);
            e("Server closed", 'red');
        }

    }

    /**
     * Destruct
     *
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }
}
