<?php
namespace Kemer\Streaming\Handler;

use Kemer\Stream\Exceptions\SocketException;
use Kemer\Stream\StreamInterface;

abstract class AbstractHandler implements HandlerInterface
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
     * @param StreamInterface $stream
     */
    public function __construct(StreamInterface $stream)
    {
        $this->setStream($stream);
        pcntl_signal(SIGUSR1, [$this, "signalHandler"]);
        pcntl_signal(SIGTERM, [$this, "signalHandler"]);

    }

    /**
     * Server constructor
     *
     * @param string $url
     */
    public function signalHandler($signal)
    {
        print "Caught SIGAL $signal\n";
        switch ($signal) {
            case SIGTERM: // handle shutdown tasks
                $this->close();
                exit;
                break;
            case SIGHUP: // handle restart tasks
                $this->close();
                break;
            case SIGUSR1:
                $this->close();
                exit;
                break;
            default: // handle all other signals
                $this->close();
        }
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
     * Handle connection
     *
     * @param StreamInterface $connection
     * @return void
     */
    public function handle(StreamInterface $client)
    {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $ip = $client->getMetadata("uri");

        // $this->openConnection($client);

        // while ($chunk = $this->stream->read($this->buffer)) {
        //     try {
        //         $client->write($chunk);
        //     } catch (\ErrorException $e) {
        //         $this->stream->close();
        //         e("A client disconnected: $ip", 'red');
        //         exit(-1);
        //     }
        // }
        // $this->closeConnection($client);
        // return;

        $this->addClient($client->getResource());

        while($this->running) {
            if (empty($this->clients)) {
                $this->close();
                exit(2);
            }
            $this->loop($this->stream);
        }
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
        $write = $this->clients;

        if (stream_select($read, $write, $except, 0) > 0) {

            if (!empty($read)) {
                $this->read($read);
            }

            if (!empty($write)) {
                try {
                    $chunk = $stream->read($this->buffer);
                    //$chunk = $stream->getContents();
                    //$this->running = false;
                } catch (ErrorException $e) {
                    $this->stream->close();
                    e("A client disconnected: $ip", 'red');
                    exit(-1);
                }
                $this->write($write, $chunk);
            }
        } else {
            $chunk = $stream->read($this->buffer);
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
        $this->running = false;
        foreach ($this->clients as &$client) {
            $this->closeConnection($client);
            $this->removeClient($client);
        }
        if ($this->stream instanceof StreamInterface) {
            $this->stream->close();
            $this->stream = null;
            e("Stream closed", 'red');
        }
        if ($this->redis instanceof \Redis) {
            $this->redis->close();
            $this->redis = null;
            e("Redis closed", 'red');
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
