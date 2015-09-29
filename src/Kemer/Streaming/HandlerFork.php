<?php
namespace Kemer\Streaming;

use Zend\Http\Request;
use Zend\Http\Response;

class HandlerFork
{
    /**
     * @var HandlerFactory
     */
    protected $factory;

    public function __construct(HandlerFactory $factory = null)
    {
        $this->factory = $factory;
    }

    /**
     * Request handler
     *
     * @param string $request
     * @param array $routing
     * @return void
     */
    public function start($uri, $client)
    {
        if (!$pid = pcntl_fork()) {
            $factory = new HandlerFactory();
            $handler = $factory->chunkedHandler($uri);
            $handler->handle($client);
        } else {
            return $pid;
        }

    }
}
