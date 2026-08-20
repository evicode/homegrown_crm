<?php

declare(strict_types=1);

use Dreamsmith\Campaign\Bootstrap;
use Dreamsmith\Campaign\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$application = Bootstrap::create(dirname(__DIR__));
$application->run(Request::fromGlobals())->send();
