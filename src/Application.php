<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign;

use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Support\Logger;
use Dreamsmith\Campaign\Support\View;
use Throwable;

final class Application
{
    public function __construct(
        private readonly Router $router,
        private readonly Logger $logger,
        private readonly bool $debug,
        private readonly ?View $view = null,
        private readonly array $securityHeaders = [],
    )
    {
    }

    public function run(Request $request): Response
    {
        try {
            return $this->secure($this->router->dispatch($request)->withHeader('X-Request-ID', $request->requestId));
        } catch (Throwable $exception) {
            $this->logger->error('Unhandled request exception', ['request_id' => $request->requestId, 'exception' => $exception]);
            $detail = $this->debug ? $exception->getMessage() : 'An unexpected error occurred.';
            $externalRequest = preg_match('#/(?:api(?:/|$)|mcp/?$)#', $request->path) === 1;
            if (!$externalRequest && $this->view !== null) {
                return $this->secure(Response::html($this->view->render('errors/500.php', ['requestId' => $request->requestId]), 500)
                    ->withHeader('X-Request-ID', $request->requestId));
            }
            return $this->secure(Response::json(['error' => 'internal_error', 'detail' => $detail, 'request_id' => $request->requestId], 500)
                ->withHeader('X-Request-ID', $request->requestId));
        }
    }

    private function secure(Response $response): Response
    {
        foreach ($this->securityHeaders as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}
