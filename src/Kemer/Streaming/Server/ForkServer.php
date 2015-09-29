<?php
namespace Kemer\Streaming\Server;

use Kemer\Server\CallbackFork;


class ForkServer extends AbstractServer
{
    /**
     * {@inheritDoc}
     */
    public function createServer($contentType, $port)
    {
        $server = $this->getFactory()->liveStream($this->getUri(), $port);

        $fork = new CallbackFork([$server, "run"]);
        $fork->call($port, $this->getHost());

        $this->addServer($contentType, sprintf("http://%s:%s", $this->getHost(), $port));
        return true;
    }
}
