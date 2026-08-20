<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Campaign;

use DateTimeZone;
use Dreamsmith\Campaign\Authentication\AuthenticationService;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Presentation\FlashBag;
use Dreamsmith\Campaign\Presentation\ShellRenderer;
use Dreamsmith\Campaign\Security\Csrf;

final class CampaignController
{
    public function __construct(
        private readonly AuthenticationService $auth,
        private readonly Database $database,
        private readonly CampaignService $service,
        private readonly ShellRenderer $shell,
        private readonly FlashBag $flash,
        private readonly Csrf $csrf,
        private readonly Router $router,
    ) {
    }

    public function index(): Response
    {
        $user = $this->user();
        if ($user instanceof Response) {
            return $user;
        }
        $repository = new CampaignRepository($this->database->pdo());
        return $this->shell->owner('campaign/index.php', [
            'campaigns' => $repository->all(),
            'settings' => $repository->settings(),
            'newUrl' => $this->router->url('campaigns.new'),
            'editUrl' => fn (int $id): string => $this->router->url('campaigns.edit', ['id' => $id]),
            'activateUrl' => fn (int $id): string => $this->router->url('campaigns.activate', ['id' => $id]),
            'timezoneUrl' => $this->router->url('settings.timezone'),
            'csrfToken' => $this->csrf->token(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ], $user, 'Campaigns', 'campaign.settings');
    }

    public function shortcut(): Response
    {
        $user = $this->user();
        if ($user instanceof Response) {
            return $user;
        }
        $settings = (new CampaignRepository($this->database->pdo()))->settings();
        return $settings['active_campaign_id'] === null
            ? Response::redirect($this->router->url('campaigns.new'), 302)
            : Response::redirect($this->router->url('campaigns.edit', ['id' => (int) $settings['active_campaign_id']]), 302);
    }

    public function createForm(array $values = [], array $errors = [], int $status = 200): Response
    {
        $user = $this->user();
        if ($user instanceof Response) {
            return $user;
        }
        return $this->form($user, null, $values ?: $this->defaults(), $errors, [], $status);
    }

    public function create(Request $request): Response
    {
        $user = $this->user();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) {
            return Response::html('Forbidden', 403);
        }
        [$input, $errors] = CampaignInput::fromArray($request->body);
        if ($input === null) {
            return $this->form($user, null, $request->body, $errors, [], 422);
        }
        $id = $this->service->create($input, (int) $user['id'], $request->requestId);
        $this->flash->add('success', 'Campaign created. Activate it when you are ready to use it.');
        return Response::redirect($this->router->url('campaigns.edit', ['id' => $id]));
    }

    public function editForm(int $id, array $errors = [], int $status = 200, ?array $submittedValues = null): Response
    {
        $user = $this->user();
        if ($user instanceof Response) {
            return $user;
        }
        $repository = new CampaignRepository($this->database->pdo());
        $campaign = $repository->find($id);
        if ($campaign === null) {
            return Response::html('Campaign not found.', 404);
        }
        $targets = $repository->targets($id);
        $savedValues = [
            'name' => $campaign['name'], 'start_date' => $campaign['start_date'], 'end_date' => $campaign['end_date'],
            'targets' => array_map(static fn (array $target): int => (int) $target['target_value'], $targets),
        ];
        return $this->form($user, $campaign, $submittedValues ?? $savedValues, $errors, $targets, $status);
    }

    public function update(Request $request, int $id): Response
    {
        $user = $this->user();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) {
            return Response::html('Forbidden', 403);
        }
        [$input, $errors] = CampaignInput::fromArray($request->body);
        if ($input === null) {
            return $this->editForm($id, $errors, 422, $request->body);
        }
        $versions = is_array($request->body['target_versions'] ?? null) ? array_map('intval', $request->body['target_versions']) : [];
        try {
            $this->service->update($id, $input, (int) ($request->body['version'] ?? 0), $versions, (int) $user['id'], $request->requestId);
        } catch (StaleCampaignVersion $exception) {
            return $this->editForm($id, ['conflict' => $exception->getMessage()], 409);
        }
        $this->flash->add('success', 'Campaign settings saved.');
        return Response::redirect($this->router->url('campaigns.edit', ['id' => $id]));
    }

    public function activate(Request $request, int $id): Response
    {
        $user = $this->user();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) {
            return Response::html('Forbidden', 403);
        }
        try {
            $this->service->activate($id, (int) ($request->body['settings_version'] ?? 0), (int) $user['id'], $request->requestId);
            $this->flash->add('success', 'Active campaign changed.');
        } catch (StaleCampaignVersion $exception) {
            $this->flash->add('warning', $exception->getMessage() . ' Review the current selection.');
        }
        return Response::redirect($this->router->url('campaigns.index'));
    }

    public function timezone(Request $request): Response
    {
        $user = $this->user();
        if ($user instanceof Response) {
            return $user;
        }
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) {
            return Response::html('Forbidden', 403);
        }
        try {
            $this->service->updateTimezone(
                is_string($request->body['timezone'] ?? null) ? $request->body['timezone'] : '',
                ($request->body['confirm_timezone'] ?? null) === '1',
                (int) ($request->body['settings_version'] ?? 0),
                (int) $user['id'],
                $request->requestId,
            );
            $this->flash->add('success', 'Owner timezone updated.');
        } catch (\InvalidArgumentException|StaleCampaignVersion $exception) {
            $this->flash->add('error', $exception->getMessage());
        }
        return Response::redirect($this->router->url('campaigns.index'));
    }

    /** @param array<string,mixed> $user @param array<string,mixed>|null $campaign @param array<string,mixed> $values @param array<string,string> $errors @param array<string,array<string,mixed>> $targets */
    private function form(array $user, ?array $campaign, array $values, array $errors, array $targets, int $status): Response
    {
        $editing = $campaign !== null;
        return $this->shell->owner('campaign/form.php', [
            'campaign' => $campaign,
            'values' => $values,
            'errors' => $errors,
            'targetDefinitions' => CampaignMetrics::definitions(),
            'targets' => $targets,
            'actionUrl' => $editing ? $this->router->url('campaigns.update', ['id' => $campaign['id']]) : $this->router->url('campaigns.create'),
            'indexUrl' => $this->router->url('campaigns.index'),
            'csrfToken' => $this->csrf->token(),
            'editing' => $editing,
        ], $user, $editing ? 'Edit campaign' : 'New campaign', 'campaign.settings', $status);
    }

    /** @return array<string,mixed>|Response */
    private function user(): array|Response
    {
        return $this->auth->user() ?? Response::redirect($this->router->url('login.form') . '?return=dashboard', 302);
    }

    /** @return array<string,mixed> */
    private function defaults(): array
    {
        $settings = (new CampaignRepository($this->database->pdo()))->settings();
        $today = new \DateTimeImmutable('today', new DateTimeZone((string) $settings['owner_timezone']));
        return ['name' => '30-day contract campaign', 'start_date' => $today->format('Y-m-d'), 'end_date' => $today->modify('+29 days')->format('Y-m-d'), 'targets' => CampaignMetrics::defaults()];
    }
}
