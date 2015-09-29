<?php
namespace Kemer\Streaming\Server;

use Psr\Http\Message\StreamInterface;
use Kemer\Streaming;

abstract class AbstractServer
{
    /**
     * @var array
     */
    private $servers = [];

    /**
     * @var string
     */
    private $uri;

    /**
     * @var string
     */
    private $host;

    /**
     * @var ServerFactory
     */
    private $factory;

    /**
     * Controller constructor
     *
     * @param string $uri
     * @param string $host
     */
    public function __construct($uri, $host = null)
    {
        $this->factory = new Streaming\ServerFactory();
        $this->uri = $uri;
        $this->host = $host ?: $this->factory->getIp();
    }

    /**
     * Returns server source uri
     *
     * @return string
     */
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Returns server source uri
     *
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Returns server host
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Check if server exists
     *
     * @param string $contentType
     * @return bool
     */
    public function hasServer($contentType)
    {
        if (empty($contentType)) {
            throw new \Exception("Missing target content-type");
        }
        return $this->getServer($contentType) !== null;
    }

    /**
     * Returns server url
     *
     * @param string $contentType
     * @return bool
     */
    public function getServer($contentType)
    {
        return isset($this->servers[$contentType]) ? $this->servers[$contentType] : null;

    }

    /**
     * Returns all servers
     *
     * @return array
     */
    public function getServers()
    {
        return $this->servers;
    }

    /**
     * Add server url
     *
     * @param string $contentType
     * @param string $url
     * @return this
     */
    public function addServer($contentType, $url)
    {
        $this->servers[$contentType] = $url;
        return $this;
    }

    /**
     * Create new server
     *
     * @param string $contentType
     * @param integer $port
     * @return this
     */
    abstract public function createServer($contentType, $port);
}
