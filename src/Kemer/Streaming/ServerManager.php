<?php
namespace Kemer\Streaming;

class ServerManager
{
    /**
     * @var array
     */
    private $servers = [];

    /**
     * @var integer
     */
    private $port = 9090;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $defaultType = "mpegts";

    /**
     * @var Redis
     */
    private $redis;
    /**
     * Manager constructor
     *
     * @param string $host
     */
    public function __construct($host)
    {
        $this->host = $host;
        // $this->redis = new \Redis();
        // $this->redis->connect('127.0.0.1', 6379);
        //$this->redis->subscribe(['server-1'], [$this, "redisCallback"]);
    }

    /**
     * Check if server exists
     *
     * @param string $url
     * @param string $contentType
     * @return bool
     */
    public function hasServer($url, $contentType = null)
    {
        //$this->redis->publish('server-1', 'hello, world!');
        if (empty($url)) {
            throw new \Exception("Missing source url");
        }
        if (empty($contentType)) {
            $contentType = $this->defaultType;
        }
        return $this->getServer($url, $contentType);
    }

    /**
     * Returns server uri
     *
     * @param string $url
     * @param string $contentType
     * @return string
     */
    public function getServer($url, $contentType = null)
    {
        if (empty($contentType)) {
            $contentType = $this->defaultType;
        }
        if (isset($this->servers[$url])) {
            return $this->servers[$url]->getServer($contentType);
        }
    }

    /**
     * Returns all servers
     *
     * @return array
     */
    public function getServers()
    {
        $servers = [];
        foreach ($this->servers as $url => $server) {
            $servers[$url] = $server->getServers();
        }
        return $servers;
    }

    /**
     * Create new server
     *
     * @param string $url
     * @param string $contentType
     * @return this
     */
    public function createServer($url, $contentType = null)
    {
        if (empty($contentType)) {
            $contentType = $this->defaultType;
        }
        if ($server = $this->getServer($url, $contentType)) {
            return $server;
        }

        if (!($server = $this->getServer($url))) {
            $server = $this->servers[$url] = new Server\ForkServer($url, $this->host);
        }
        return $server->createServer($contentType, $this->port++);
    }
}
