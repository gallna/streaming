<?php
namespace Kemer\Stream\Handler;

use Psr\Http\Message\StreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Http\PhpEnvironment\Response as PhpResponse;
use Kemer\Stream\Stream;

class RawHandler
{
    private $contentType = "video/mp4";
    private $stream;
    private $buffer = 4096;
    private $start = 0;
    private $pipes;
    private $process;

    public function __construct(StreamInterface $stream = null)
    {
        //$stream->seek(50000);
        $this->stream = $stream;

        if ($contentType = $stream->getMetadata("content-type")) {
            $this->contentType = $contentType;
        }
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function getSize()
    {
        return $this->stream->getSize();
    }

    public function handle(Request $request, $conn)
    {
        $response = $this->getResponse();
        e($response, 'blue');
        $conn->write($response->toString());
        $command = 'ffmpeg -re -f mpegts -i pipe: -c copy -bsf:a aac_adtstoasc -movflags empty_moov+frag_keyframe -f mp4 pipe:';

        $pipeStream = new Stream\PipeStream($this->stream, $command);
        $chunkedStream = new Stream\ChunkedStream($pipeStream);
        while ($chunk = $chunkedStream->read($this->buffer)) {
            $conn->write($chunk);
        }
        $conn->write("0\r\n\r\n");
        $this->close();
    }

    public function handlez(Request $request, $conn)
    {
        $response = $this->getResponse();
        e($response, 'blue');
        $conn->write($response->toString());
        return $this->getContent($conn);
    }

    public function getContent($conn)
    {
        try {
            while ($chunk = $this->stream->read($this->buffer)) {
                $this->writeChunk($chunk, $conn);
            }
            $conn->write("0\r\n\r\n");
        } catch (\ErrorException $e) {
            $this->close();
            if (preg_match("/^fwrite\(\): send of (\d+) bytes failed with errno=([0-9]+) ([A-Za-z \/]+)$/",$e->getMessage(), $matches)) {
                if ($matches[2] == 32) {
                    e($e->getMessage(), 'red');
                    return;
                }
            }
            throw $e;
        } finally {
            $this->close();
        }

    }

    protected function writeChunk($chunk, $conn)
    {
        $pipes = $this->getPipes();
        while ($chunk) {
            $write  = array($pipes[0]);
            $read   = array($pipes[1]);
            $except = null;
            if(stream_select($read, $write, $except, null, 0) > 0) {
                if (isset($read[0]) && $read[0] == $pipes[1]) {
                    $decoded = stream_get_contents($pipes[1]);
                    $hex = dechex(strlen($decoded));
                    $conn->write($hex."\r\n".$decoded."\r\n");
                }
                if (isset($write[0]) && $write[0] == $pipes[0]) {
                    fwrite($pipes[0], $chunk);
                    $chunk = false;
                }
            }
        }
    }

    private function getPipes()
    {
        if (!$this->pipes) {
            $this->pipes = $this->createProcess();
        }
        return $this->pipes;
    }

    private function createProcess()
    {
        //$command = "ffmpeg -re -i - -c:v libx264 -s 640x360 -vb 512k -bufsize 1024k -maxrate 512k -level 31 -keyint_min 25 -g 25 -sc_threshold 0 -bsf h264_mp4toannexb -flags -global_header -movflags empty_moov+frag_keyframe -pass 1 -f mp4 pipe:";
        $command = "ffmpeg -re -i - -c:a copy -bsf:a aac_adtstoasc -c:v copy -movflags empty_moov+frag_keyframe  -bufsize 1024k -f mp4 pipe:";
        // ffmpeg -i http://localhost:8080/file.mp4 -c copy -f mpegts -y /data/www/segments/stream.ts
        $command = 'ffmpeg -re -f mpegts -i pipe: -c copy -bsf:a aac_adtstoasc -movflags empty_moov+frag_keyframe -y -f mp4 pipe:';
        $descriptorspec = array(
           0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
           1 => array("pipe", "w"), //$stream,  // stdout is a pipe that the child will write to
           2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
        );

        $this->process = proc_open($command, $descriptorspec, $pipes);
        if (!is_resource($this->process)) {
            return;
        }

        stream_set_blocking($pipes[0], 0);
        stream_set_blocking($pipes[1], 0);

        return $pipes;
    }

    public function close()
    {
        if (is_resource($this->pipes[0])) {
            fclose($this->pipes[0]);
        }
        if (is_resource($this->pipes[1])) {
            fclose($this->pipes[1]);
        }
        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        if (is_resource($this->process)) {
            proc_close($this->process);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function getResponse()
    {
        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_200);
        $response->getHeaders()->addHeaders([
            'Content-Type' => $this->getContentType(),
            'Accept-Ranges' => 'bytes',
            'Transfer-Encoding' => 'chunked',
        ]);
        //$response->setContent($this->getContent($start, $length));
        return $response;
    }
}
