<?php
namespace Kemer\Streaming;

use Zend\Http\Response;
use Kemer\Stream;
use Kemer\Stream\Buffer;
use Kemer\Stream\Pipe;
use Psr\Http\Message\StreamInterface;

class HandlerFactory
{

    /**
     * Returns local ip address
     *
     * @return string
     */
    public function getIp()
    {
        return $localIP = exec(
            "/sbin/ifconfig eth0 | grep 'inet addr:' | cut -d: -f2 | awk '{ print $1}'"
        );
    }

    /**
     * Return http stream
     *
     * @param string $url
     * @return HttpStream
     */
    public function httpStream($url)
    {
        return new Stream\HttpStream($url);
    }

    /**
     * Returns mpegts stream
     *
     * @param string $url
     * @return ReadPipeStream
     */
    public function mpegtsStream($url)
    {
        $buffer = new Buffer\PreBuffer();
        $command = 'ffmpeg -re -f mpegts -i pipe: -c copy -f mpegts pipe:';
        return new Pipe\PipeStream($this->httpStream($url), $command, $buffer);
    }

    /**
     * Returns mp4 stream
     *
     * @param string $url
     * @return ReadPipeStream
     */
    public function mp4Stream($url)
    {
        $command = 'ffmpeg -re -f mpegts -i pipe: -c copy -bsf:a aac_adtstoasc -movflags empty_moov+frag_keyframe -f mp4 pipe:';
        return new Pipe\PipeStream($this->httpStream($url), $command);
    }

    /**
     * Create chunk handler
     *
     * @param string $url
     * @return ChunkedHandler
     */
    public function chunkedHandler($url)
    {
        $stream = $this->mp4Stream($url);
        return new Handler\ChunkedHandler($stream);
    }

    /**
     * Create live handler
     *
     * @param string $url
     * @return LiveStreamServer
     */
    public function liveHandler($url)
    {
        $stream = $this->mp4Stream($url);
        return new Handler\LiveHandler($stream);
    }
}
