<?php
namespace Kemer\Stream\Handler;

use Psr\Http\Message\StreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Http\PhpEnvironment\Response as PhpResponse;

class ChunkHandler
{
    private $contentType = "video/mp4";
    private $stream;
    private $buffer = 4096;
    private $start = 0;
    private $pipes;
    private $process;

    public function __construct(StreamInterface $stream = null)
    {
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
        //return 2147483647;
        return $this->stream->getSize();
    }



    public function handle(Request $request, $conn)
    {
        $response = $this->getResponse();
        //e($response, 'blue');
        //$response->setContent($this->getContent($this->start, $this->start + $this->buffer));

        $conn->write($response->toString());
        return $this->getContent($conn);
        //$command = "ffmpeg -re -i - -c:v libx264 -s 640x360 -vb 512k -bufsize 1024k -maxrate 512k -level 31 -keyint_min 25 -g 25 -sc_threshold 0 -bsf h264_mp4toannexb -flags -global_header -movflags empty_moov+frag_keyframe -pass 1 -f mp4 pipe:";
        $command = "ffmpeg -re -i - -c:a copy -bsf:a aac_adtstoasc -c:v copy -movflags empty_moov+frag_keyframe  -bufsize 1024k -f mp4 pipe:";


        $stream = fopen(__DIR__."/cache.bin", "w+");

        $descriptorspec = array(
           0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
           1 => array("pipe", "w"), //$stream,  // stdout is a pipe that the child will write to
           2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
        );

        $cwd = '/tmp'; // The initial working dir for the command.
        $env = null; // An array with the environment variables for the command that will be run

        $process = proc_open($command, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            return;
        }
        stream_set_blocking($pipes[0], 0);
        stream_set_blocking($pipes[1], 0);
        //$stream = $pipes[1];

        $chunksSent = 0;
        $i = 0;
        //$this->stream->seek($this->buffer);
        //while ($i < 100) { $chunk = $this->stream->read($this->buffer);
        while (false !== ($chunk = $this->stream->read($this->buffer))) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable handle connected to child stdout
            // Any error output will be appended to /tmp/error-output.txt
            //$chunk = $this->getContent($this->start, $this->start + $this->buffer);
            while ($chunk) {
                $write  = array($pipes[0]);
                $read   = array($pipes[1]);
                $except = null;
                if(stream_select($read, $write, $except, null, 0) > 0) {

                    if (isset($read[0]) && $read[0] == $pipes[1]) {
                        $decoded = stream_get_contents($pipes[1]);
                        $hex = dechex(strlen($decoded));
                        echo "reading\n";
                        $conn->write($hex."\r\n".$decoded."\r\n");
                    }
                    if (isset($write[0]) && $write[0] == $pipes[0]) {
                        echo "writing\n";
                        fwrite($pipes[0], $chunk);
                        $chunk = false;
                    }
                } else {
                    echo "do nothing\n";
                }
            }


            // $temp = fopen("php://temp", "w+");
            // stream_copy_to_stream($stream, $temp, -1, $chunksSent+1);
            // rewind($temp);
            //while(!feof($stream)) {
                //sleep(0.2);
                // $decoded = $decoded($stream, $this->buffer);
                // if (strlen($decoded) > 0) {
                //     echo $chunksSent."/".$this->buffer."\n";
                //     $chunksSent += $this->buffer;
                //     $hex = dechex(strlen($decoded));
                //     $conn->write($hex."\r\n".$decoded."\r\n");
                // }
            //}
            $i++;
        }
        $conn->write("0\r\n\r\n");
        fclose($pipes[0]);
        fclose($stream);
        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        $return_value = proc_close($process);

    }

    public function getContent($conn)
    {
        while ($this->write($conn)) {

        }
    }

    public function write($conn)
    {
        if ($chunk = $this->stream->read($this->buffer)) {
            $this->writeChunk($chunk, $conn);
            return [$this, "write"];
        }
        $conn->write("0\r\n\r\n");
        $this->close();
    }

    public function writeChunk($chunk, $conn)
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
                    echo "reading\n";
                    $conn->write($hex."\r\n".$decoded."\r\n");
                }
                if (isset($write[0]) && $write[0] == $pipes[0]) {
                    //echo "writing\n";
                    fwrite($pipes[0], $chunk);
                    $chunk = false;
                }
            } else {
                echo "do nothing\n";
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
        fclose($this->pipes[0]);
        fclose($this->pipes[1]);
        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        proc_close($this->process);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function handleWork(Request $request, StreamInterface $conn)
    {
        $response = $this->getResponse();
        //e($response, 'blue');
        //$response->setContent($this->getContent($this->start, $this->start + $this->buffer));

        $conn->write($response->toString());

        //$command = "ffmpeg -re -i - -c:v libx264 -s 640x360 -vb 512k -bufsize 1024k -maxrate 512k -level 31 -keyint_min 25 -g 25 -sc_threshold 0 -bsf h264_mp4toannexb -flags -global_header -movflags empty_moov+frag_keyframe -pass 1 -f mp4 pipe:";
        $command = "ffmpeg -re -i - -c:a copy -bsf:a aac_adtstoasc -c:v copy -movflags empty_moov+frag_keyframe  -bufsize 1024k -f mp4 pipe:";


        $stream = fopen(__DIR__."/cache.bin", "w+");

        $descriptorspec = array(
           0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
           1 => array("pipe", "w"), //$stream,  // stdout is a pipe that the child will write to
           2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
        );

        $cwd = '/tmp'; // The initial working dir for the command.
        $env = null; // An array with the environment variables for the command that will be run

        $process = proc_open($command, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            return;
        }
        stream_set_blocking($pipes[0], 0);
        stream_set_blocking($pipes[1], 0);
        //$stream = $pipes[1];

        $chunksSent = 0;
        $i = 0;
        //$this->stream->seek($this->buffer);
        //while ($i < 100) { $chunk = $this->stream->read($this->buffer);
        while (false !== ($chunk = $this->stream->read($this->buffer))) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable handle connected to child stdout
            // Any error output will be appended to /tmp/error-output.txt
            //$chunk = $this->getContent($this->start, $this->start + $this->buffer);
            while ($chunk) {
                $write  = array($pipes[0]);
                $read   = array($pipes[1]);
                $except = null;
                if(stream_select($read, $write, $except, null, 0) > 0) {

                    if (isset($read[0]) && $read[0] == $pipes[1]) {
                        $decoded = stream_get_contents($pipes[1]);
                        $hex = dechex(strlen($decoded));
                        echo "reading\n";
                        $conn->write($hex."\r\n".$decoded."\r\n");
                    }
                    if (isset($write[0]) && $write[0] == $pipes[0]) {
                        echo "writing\n";
                        fwrite($pipes[0], $chunk);
                        $chunk = false;
                    }
                } else {
                    echo "do nothing\n";
                }
            }


            // $temp = fopen("php://temp", "w+");
            // stream_copy_to_stream($stream, $temp, -1, $chunksSent+1);
            // rewind($temp);
            //while(!feof($stream)) {
                //sleep(0.2);
                // $decoded = $decoded($stream, $this->buffer);
                // if (strlen($decoded) > 0) {
                //     echo $chunksSent."/".$this->buffer."\n";
                //     $chunksSent += $this->buffer;
                //     $hex = dechex(strlen($decoded));
                //     $conn->write($hex."\r\n".$decoded."\r\n");
                // }
            //}
            $i++;
        }
        $conn->write("0\r\n\r\n");
        fclose($pipes[0]);
        fclose($stream);
        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        $return_value = proc_close($process);

    }

    protected function check($pipes, $conn, $chunk)
    {

            $write  = array($pipes[0]);
            $read   = array($pipes[1]);
            $except = null;
            if(stream_select($read, $write, $except, null, 0) > 0) {
                if ($read[0] == $pipes[1]) {
                    $decoded = stream_get_contents($pipes[1]);
                    $hex = dechex(strlen($decoded));
                    echo $hex."\n";
                    echo "reading\n";
                    $conn->write($hex."\r\n".$decoded."\r\n");
                }
                if (!empty($write) && $write[0] == $pipes[0]) {
                    echo "writing\n";
                    fwrite($pipes[0], $chunk);
                }
            } else {
                echo "do nothing\n";
            }

    }

    public function hhandle(Request $request, StreamInterface $conn)
    {
        $response = $this->getResponse();
        //e($response, 'blue');
        //$response->setContent($this->getContent($this->start, $this->start + $this->buffer));
        $chunk = $this->stream->read($this->buffer);
        $hex = dechex(strlen($chunk));
        $response->setContent($hex."\r\n".$chunk."\r\n");
        $conn->write($response->toString());

        while (false !== ($chunk = $this->stream->read($this->buffer))) {
            $hex = dechex(strlen($chunk));
            $conn->write($hex."\r\n".$chunk."\r\n");
        }
        $conn->write("0\r\n\r\n");
        return;
        //$command = "ffmpeg -re -i - -c:v libx264 -s 640x360 -vb 512k -bufsize 1024k -maxrate 512k -level 31 -keyint_min 25 -g 25 -sc_threshold 0 -bsf h264_mp4toannexb -flags -global_header -movflags empty_moov+frag_keyframe -pass 1 -f mp4 pipe:";
        $command = "ffmpeg -re -i - -c:a copy -bsf:a aac_adtstoasc -c:v copy -movflags empty_moov+frag_keyframe  -bufsize 1024k -f mp4 pipe:";


        $stream = fopen(__DIR__."/cache.bin", "w+");

        $descriptorspec = array(
           0 => array("pipe", "rw"),  // stdin is a pipe that the child will read from
           1 => $stream,  // stdout is a pipe that the child will write to
           2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
        );

        $cwd = '/tmp'; // The initial working dir for the command.
        $env = null; // An array with the environment variables for the command that will be run

        $process = proc_open($command, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            return;
        }

        $chunksSent = 0;
        $i = 0;
        //$this->stream->seek($this->buffer);
        //while ($i < 100) { $chunk = $this->stream->read($this->buffer);
        while (false !== ($chunk = $this->stream->read($this->buffer))) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable handle connected to child stdout
            // Any error output will be appended to /tmp/error-output.txt
            //$chunk = $this->getContent($this->start, $this->start + $this->buffer);

            fwrite($pipes[0], $chunk);

            fseek($stream, $chunksSent * $this->buffer);
            while(!feof($stream)) {
                $decoded = fread($stream, $this->buffer);
                if (strlen($decoded) == $this->buffer) {
                    echo $chunksSent."/".$this->buffer."\n";
                    $chunksSent ++;
                    $hex = dechex(strlen($decoded));
                    $conn->write($hex."\r\n".$decoded."\r\n");
                }
            }
            $i++;
        }
        $conn->write("0\r\n\r\n");
        fclose($pipes[0]);
        fclose($stream);
        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        $return_value = proc_close($process);

    }

    function fwrite_stream($fp, $string) {
        for ($written = 0; $written < strlen($string); $written += $fwrite) {
            $fwrite = fwrite($fp, substr($string, $written));
            if ($fwrite === false) {
                return $written;
            }
        }
        return $written;
    }

    private function createPipe($command)
    {
        $descriptorspec = array(
           0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
           1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
           2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
        );

        $cwd = '/tmp'; // The initial working dir for the command.
        $env = null; // An array with the environment variables for the command that will be run

        $process = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

        if (is_resource($process)) {
            // $pipes now looks like this:
            // 0 => writeable handle connected to child stdin
            // 1 => readable handle connected to child stdout
            // Any error output will be appended to /tmp/error-output.txt


            fclose($pipes[0]);
            echo fread($pipes[1], 1000);

            echo stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // It is important that you close any pipes before calling
            // proc_close in order to avoid a deadlock
            $return_value = proc_close($process);

            echo "command returned $return_value\n";
        }
    }

// ffmpeg -re -i http://10.0.10.107:8080/file.mp4 -g 52 -vcodec copy -an -f mp4 -reset_timestamps 1 -movflags empty_moov+frag_keyframe vlc.mp4

    private function getResponse()
    {

        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_200);
        $response->getHeaders()->addHeaders([
            'Content-Type' => $this->getContentType(),
            //'Content-Type' => "octet/stream",
            'Accept-Ranges' => 'bytes',
            'Transfer-Encoding' => 'chunked',
            //"Cache-Control" => "no-cache",
            //'Cache-Control' => 'max-age=2592000, public',
            //"Expires" => gmdate('D, d M Y H:i:s', time()+2592000) . ' GMT',
            //"Last-Modified" => gmdate('D, d M Y H:i:s', strtotime("today")) . ' GMT',
            //'Content-Length' => 2147483647,
            //'Content-Length' => $length = $end - $start,
            //'Content-Range' => sprintf("bytes %d-%d/%d", $start, $end - 1, $this->getSize()),
        ]);
        //$response->setContent($this->getContent($start, $length));
        return $response;
    }

    /**
     * perform the streaming of calculated range
     */
    private function getContentss($start, $end)
    {
        if ($this->stream->getSize() < $start + $end) {
            sleep(1);
        }
        $this->stream->seek($start);
        return $this->stream->read($end - $start);
    }

    private function getRangeError($start, $end)
    {
        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_416);
            $response->getHeaders()->addHeaders([
                'Content-Length' => $length = $this->getSize(),
                'Content-Range' => sprintf("bytes %d-%d/%d", $start, $end - 1, $this->getSize()),
            ]);
        return $response;
    }
}
