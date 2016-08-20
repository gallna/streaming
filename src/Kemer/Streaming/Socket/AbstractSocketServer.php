<?php
declare(ticks = 1);

namespace Kemer\Streaming\Socket;

use Zend\Http\Response;
use Kemer\Stream\Stream;
use Kemer\Stream\Exceptions\SocketException;
use Kemer\Stream\Exceptions\ProcessException;
use Psr\Http\Message\StreamInterface;

abstract class AbstractSocketServer
{
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
     * @var integer
     */
    private $skipped = 0;

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
     * Run server
     *
     * @param integer $port
     * @param string $host
     * @return void
     */
    public function run($port, $host)
    {
        $this->startServer($port, $host);
        while($this->running) {
            $this->loop();
        }
    }

    /**
     * Loop over existing connections
     *
     * @param StreamInterface $stream
     * @return void
     */
    private function loop()
    {
        $read = $this->clients;
        $read[] = $this->server;
        $write = $this->clients;

        if (0 !== ($num = stream_select($read, $write, $except, 2000))) {
            //new client
            if (in_array($this->server, $read)) {
                if ($client = stream_socket_accept($this->server)) {
                    $this->addClient($client);
                }
                //delete the server socket from the read sockets
                unset($read[array_search($this->server, $read)]);
            }

            if (!empty($read)) {
                $this->onRead($read);
            }

            if (!empty($write)) {
                $this->onWrite($write);
            }
        } elseif($num === false) {
            echo "stream_select() failed\n";
        }
    }

    /**
     * Adds new client
     *
     * @param resource $client
     */
    protected function addClient($client)
    {
        stream_set_blocking($client, 0);
        $this->clientAdded($client);
        $this->clients[] = $client;
        echo "New client: ".stream_socket_get_name($client, true)."\n";
    }

    /**
     * Remove client
     *
     * @param resource $client
     */
    protected function removeClient($client)
    {
        if (is_resource($client)) {
            $this->clientRemoved($client);
            echo "Client disconnected: ".stream_socket_get_name($client, true)."\n";
            unset($this->clients[array_search($client, $this->clients)]);
            @fclose($client);
        } else {
            echo "Client already disconnected?"."\n";
        }
    }

    /**
     * Read client messages
     *
     * @param resource[] $clients
     */
    private function onRead(array $clients)
    {
        foreach($clients as $client) {
            $this->read($client);
        }
    }

    /**
     * Write chunk to each clients
     *
     * @param resource[] $clients
     * @param string $chunk
     */
    private function onWrite(array $clients)
    {
        foreach($clients as $client) {
            try {
                $this->write($client);
            } catch (\ErrorException $e) {
                $exception = new SocketException();
                if (!$exception->isConnectionClosed()) {
                    throw $exception;
                }
                $this->removeClient($client);
            }
        }
    }


    /**
     * Close server
     *
     * @return void
     */
    final public function close()
    {
        foreach ($this->clients as &$client) {
            $this->closeConnection($client);
            $this->removeClient($client);
        }
        if (is_resource($this->server)) {
            fclose($this->server);
            echo "Server closed";
        }
        $this->stream->close();
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
