<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Presentation;

use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Security\Csrf;
use Dreamsmith\Campaign\Support\View;

final class ShellRenderer
{
    public function __construct(
        private readonly View $view,
        private readonly Router $router,
        private readonly Database $database,
        private readonly FlashBag $flash,
        private readonly Csrf $csrf,
        private readonly Formatter $formatter,
        private readonly string $assetBase,
    ) {
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $user */
    public function owner(string $template, array $data, array $user, string $title, string $current, int $status = 200): Response
    {
        $settings = $this->database->pdo()->query('SELECT active_campaign_id, owner_timezone FROM application_settings WHERE id = 1')->fetch();
        if (!is_array($settings)) {
            throw new \RuntimeException('Application settings singleton is missing.');
        }
        $content = $this->view->render($template, $data + ['formatter' => new Formatter((string) $settings['owner_timezone']), 'router' => $this->router]);
        return Response::html($this->view->render('layouts/app.php', [
            'content' => $content,
            'title' => $title,
            'current' => $current,
            'user' => $user,
            'navigation' => (new NavigationBuilder($this->router))->build($current, $settings['active_campaign_id'] !== null),
            'flashes' => $this->flash->consume(),
            'assetBase' => $this->assetBase,
            'accountUrl' => $this->router->url('account.password'),
            'logoutUrl' => $this->router->url('logout'),
            'csrfToken' => $this->csrf->token(),
        ]), $status);
    }

    /** @param array<string, mixed> $data */
    public function guest(string $template, array $data, string $title, int $status = 200): Response
    {
        $content = $this->view->render($template, $data + ['formatter' => $this->formatter]);
        return Response::html($this->view->render('layouts/guest.php', [
            'content' => $content,
            'title' => $title,
            'flashes' => $this->flash->consume(),
            'assetBase' => $this->assetBase,
        ]), $status);
    }

}
