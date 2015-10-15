<?php
namespace Kemer\Streaming\Server;

use Kemer\Server\CallbackFork;


class ForkServer extends AbstractServer
{
    /**
     * @var integer
     */
    protected $pid;

    /**
     * @var integer
     */
    protected $status;

    /**
     * Returns Pidfile content with the PID or NULL when there is no stored PID.
     *
     * @return integer|null
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Returns the exit code of a terminated child.
     *
     * @return integer
     */
    public function getExitStatus()
    {
        return pcntl_wexitstatus($this->status);
    }

    /**
     * Returns TRUE when pidfile is active or FALSE when is not.
     *
     * @return boolean
     */
    public function isActive()
    {
        if (!($pid = $this->getPid())) {
            return false;
        }

        $res = pcntl_waitpid($pid, $status, WNOHANG);
        if($res == -1 || $res > 0) {
            $this->status = $status;
            $this->pid = null;
            return false;
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function createServer($contentType, $port)
    {
        $server = $this->getFactory()->liveStream($this->getUri(), $port);

        $fork = new CallbackFork([$server, "run"]);
        $this->pid = $fork->call($port, $this->getHost());

        $this->addServer($contentType, sprintf("http://%s:%s", $this->getHost(), $port));
        return true;
    }
}
