<?php
namespace Kemer\Streaming;

use Zend\Http\Response;
use Kemer\Stream\Pipe;

class ServerFactory
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
     * Return mpegts stream for url using ffmpeg
     *
     * @param string $url
     * @return ReadPipeStream
     */
    public function mpegtsFfmpegStream($url)
    {
        $command = sprintf(
            'ffmpeg -re -i "%s" -c copy -bsf:v h264_mp4toannexb -f mpegts pipe:',
            $url
        );
        return new Pipe\ReadPipeStream($command);
    }

    /**
     * Return mpegts stream for url using cvlc
     *
     * @param string $url
     * @return ReadPipeStream
     */
    public function mpegtsStream($url)
    {
        $command = sprintf(
            'exec cvlc -vvv "%s" --rate 1 --sout="#std{access=file,mux=ts,dst=\'/dev/stdout\'}" vlc://quit',
            $url
        );
        return new Pipe\ReadPipeStream($command);
    }

    /**
     * Create chunk server
     *
     * @param string $url
     * @param integer $port
     * @param string $host
     * @return ChunkedStreamServer
     */
    public function chunkedStream($url, $port, $host = null)
    {
        $host = $host ?: $this->getIp();
        $stream = $this->mpegtsFfmpegStream($url);
        return $server = new Socket\ChunkedServer($stream, $host, $port);
    }

    /**
     * Create live server
     *
     * @param string $url
     * @param integer $port
     * @param string $host
     * @return LiveStreamServer
     */
    public function liveStream($url, $port, $host = null)
    {
        $host = $host ?: $this->getIp();
        $stream = $this->mpegtsFfmpegStream($url);
        return $server = new Socket\LiveServer($stream, $host, $port);
    }
}
