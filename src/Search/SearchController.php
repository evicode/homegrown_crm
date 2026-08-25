<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Search;

use Dreamsmith\Campaign\Authentication\AuthenticationService;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Presentation\ShellRenderer;

final class SearchController
{
    public function __construct(private readonly AuthenticationService $auth, private readonly Database $database, private readonly ShellRenderer $shell, private readonly Router $router) {}

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) return Response::redirect($this->router->url('login.form') . '?return=dashboard', 302);
        $query = trim(is_string($request->query['q'] ?? null) ? $request->query['q'] : '');
        $query = substr($query, 0, 120);
        $results = strlen($query) >= 2 ? (new SearchRepository($this->database->pdo()))->search($query) : null;
        return $this->shell->owner('search/index.php', [
            'query' => $query, 'results' => $results, 'indexUrl' => $this->router->url('search.index'),
            'companyUrl' => fn (int $id): string => $this->router->url('companies.show', ['id' => $id]),
            'contactUrl' => fn (int $id): string => $this->router->url('contacts.edit', ['id' => $id]),
            'prospectUrl' => fn (int $id): string => $this->router->url('prospects.show', ['id' => $id]),
            'opportunityUrl' => fn (int $id): string => $this->router->url('opportunities.show', ['id' => $id]),
        ], $user, 'Search', 'search.index');
    }
}
