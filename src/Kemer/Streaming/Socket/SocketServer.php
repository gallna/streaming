<?php
namespace Kemer\Streaming\Socket;

/*
$server = new Kemer\Streaming\Socket\SocketServer(new class {
    public function read($data)
    {
        echo $data;
    }

    public function write($client)
    {
        fwrite($client, "Hello client");
    }

    public function __clone()
    {

    }
});
$server->run(8088, "0.0.0.0");
 */

class SocketServer extends AbstractSocketServer
{
    /**
     * @var handler
     */
    private $prototype;

    /**
     * @var handlers[]
     */
    private $handlers = [];

    /**
     * Server constructor
     *
     * @param string $prototype
     */
    public function __construct($prototype)
    {
        $this->prototype = $prototype;
    }

    /**
     * Adds new client
     *
     * @param resource $client
     */
    protected function clientAdded($client)
    {
        $this->handlers[(int)$client] = clone $this->prototype;
    }

    /**
     * Remove client
     *
     * @param resource $client
     */
    protected function clientRemoved($client)
    {
        unset($this->handlers[(int)$client]);
    }

    /**
     * Read client content
     *
     * @param resource $client
     */
    protected function read($client)
    {
        if($data = stream_get_contents($client)) {
            return $this->handlers[(int)$client]->read($data);
        }
        $this->removeClient($client);
    }

    /**
     * Write chunk to the client
     *
     * @param resource $client
     */
    protected function write($client)
    {
        if (isset($this->handlers[(int)$client])) {
            return $this->handlers[(int)$client]->write($client);
        }
    }
}
