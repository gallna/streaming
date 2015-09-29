<?php
namespace Kemer\Streaming\Server;

use Kemer\Stream\Threaded;

class ThreadServer extends AbstractServer
{
    /**
     * @var array
     */
    private $threads = [];

    /**
     * {@inheritDoc}
     */
    public function createServer($contentType, $port)
    {
        $server = new Threaded\StreamServer($this->getUri(), $port, $this->getHost());
        if ($server->start()) {
             //$server->join();
             echo "ok";
        }
        echo "failed";
        //$this->threads[$contentType] = $server;

        $this->addServer($contentType, sprintf("http://%s:%s", $this->getHost(), $port));
        return true;
    }
}
