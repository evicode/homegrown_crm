<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Authentication;

use Dreamsmith\Campaign\Campaign\CampaignRepository;
use Dreamsmith\Campaign\Http\Request;
use Dreamsmith\Campaign\Http\Response;
use Dreamsmith\Campaign\Http\Router;
use Dreamsmith\Campaign\Presentation\FlashBag;
use Dreamsmith\Campaign\Presentation\ShellRenderer;
use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Security\Csrf;
use Dreamsmith\Campaign\Reporting\CampaignReportingService;

final class AuthController
{
    /** @var array<string, string> */
    private array $returnRoutes = ['dashboard' => 'dashboard', 'account.password' => 'account.password'];

    public function __construct(
        private readonly AuthenticationService $auth,
        private readonly Csrf $csrf,
        private readonly ShellRenderer $shell,
        private readonly FlashBag $flash,
        private readonly Router $router,
        private readonly Database $database,
    ) {
    }

    public function loginForm(Request $request): Response
    {
        if ($this->auth->user() !== null) {
            return Response::redirect($this->router->url('dashboard'));
        }
        return $this->renderLogin('', '', $this->safeReturn($request->query['return'] ?? null));
    }

    public function login(Request $request): Response
    {
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) {
            return $this->renderLogin('Your form expired. Please try again.', '', $this->safeReturn($request->body['return'] ?? null), 403);
        }
        $email = is_string($request->body['email'] ?? null) ? $request->body['email'] : '';
        $password = is_string($request->body['password'] ?? null) ? $request->body['password'] : '';
        $return = $this->safeReturn($request->body['return'] ?? null);
        $result = $this->auth->login($email, $password, $request->clientIp, $request->requestId);
        if ($result === 'authenticated') {
            return Response::redirect($this->router->url($this->returnRoutes[$return]));
        }
        $message = $result === 'throttled'
            ? 'Too many attempts. Please wait before trying again.'
            : 'The email or password is incorrect.';
        return $this->renderLogin($message, $email, $return, $result === 'throttled' ? 429 : 422);
    }

    public function logout(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect($this->router->url('login.form'));
        }
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) {
            return Response::html('Forbidden', 403);
        }
        $this->auth->logout((int) $user['id'], $request->requestId);
        return Response::redirect($this->router->url('login.form'));
    }

    public function dashboard(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return $this->loginRedirect('dashboard');
        }
        $repository = new CampaignRepository($this->database->pdo());
        $settings = $repository->settings();
        $campaign = $settings['active_campaign_id'] === null ? null : $repository->find((int) $settings['active_campaign_id']);
        $report = $campaign === null ? null : (new CampaignReportingService($this->database->pdo()))->dashboard($campaign, (string) $settings['owner_timezone']);
        return $this->shell->owner('auth/dashboard.php', [
            'user' => $user,
            'campaign' => $campaign,
            'campaignSetupUrl' => $this->router->url('campaign.settings'),
            'report' => $report,
            'recentActivity' => $campaign === null ? [] : (new CampaignReportingService($this->database->pdo()))->recent((int) $campaign['id']),
            'workUrl' => $this->router->url('work.index'),
            'prospectUrl' => fn (int $id): string => $this->router->url('prospects.show', ['id' => $id]),
        ], $user, 'Dashboard', 'dashboard');
    }

    public function passwordForm(Request $request, string $error = '', string $success = ''): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return $this->loginRedirect('account.password');
        }
        return $this->shell->owner('auth/password.php', [
            'csrfToken' => $this->csrf->token(),
            'error' => $error,
            'success' => $success,
        ], $user, 'Change password', 'account.password', $error === '' ? 200 : 422);
    }

    public function changePassword(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return $this->loginRedirect('account.password');
        }
        if (!$this->csrf->verify($request->body['_csrf'] ?? null)) {
            return Response::html('Forbidden', 403);
        }
        $value = static fn (string $key): string => is_string($request->body[$key] ?? null) ? $request->body[$key] : '';
        $error = $this->auth->changePassword($user, $value('current_password'), $value('new_password'), $value('new_password_confirmation'), $request->requestId);
        if ($error === null) {
            $this->flash->add('success', 'Your password has been changed.');
            return Response::redirect($this->router->url('account.password'));
        }
        return $this->passwordForm($request, error: $error);
    }

    private function renderLogin(string $error, string $email, string $return, int $status = 200): Response
    {
        return $this->shell->guest('auth/login.php', [
            'csrfToken' => $this->csrf->token(),
            'error' => $error,
            'email' => $email,
            'return' => $return,
            'loginUrl' => $this->router->url('login.submit'),
        ], 'Sign in', $status);
    }

    private function safeReturn(mixed $candidate): string
    {
        return is_string($candidate) && isset($this->returnRoutes[$candidate]) ? $candidate : 'dashboard';
    }

    private function loginRedirect(string $return): Response
    {
        return Response::redirect($this->router->url('login.form') . '?return=' . rawurlencode($return), 302);
    }
}
