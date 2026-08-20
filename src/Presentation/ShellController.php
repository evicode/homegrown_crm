<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Presentation;

use Dreamsmith\Campaign\Authentication\AuthenticationService;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Security\Csrf;

final class ShellController
{
    public function __construct(
        private readonly AuthenticationService $auth,
        private readonly ShellRenderer $shell,
        private readonly Router $router,
        private readonly Csrf $csrf,
    ) {
    }

    public function placeholder(string $routeName, string $title, string $description): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect($this->router->url('login.form') . '?return=dashboard', 302);
        }
        return $this->shell->owner('shell/placeholder.php', [
            'heading' => $title,
            'description' => $description,
            'csrfToken' => $this->csrf->token(),
        ], $user, $title, $routeName);
    }
}
