<?php
namespace Kemer\Stream\Handler;

use Psr\Http\Message\StreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Http\PhpEnvironment\Response as PhpResponse;

interface HandlerInterface
{
    const BUFFER = 4096;

    public function handle(Request $request, $conn);
}
