<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Presentation;

use Dreamsmith\Campaign\Security\SessionManager;

final class FlashBag
{
    private const KEY = '_flash_messages';
    private const TYPES = ['success', 'warning', 'error', 'info'];

    public function __construct(private readonly SessionManager $session)
    {
    }

    public function add(string $type, string $message): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unknown flash message type.');
        }
        $messages = $this->session->get(self::KEY, []);
        $messages = is_array($messages) ? $messages : [];
        $messages[] = ['type' => $type, 'message' => $message];
        $this->session->set(self::KEY, $messages);
    }

    /** @return list<array{type:string,message:string}> */
    public function consume(): array
    {
        $messages = $this->session->get(self::KEY, []);
        $this->session->remove(self::KEY);
        return is_array($messages) ? array_values(array_filter($messages, static fn (mixed $item): bool =>
            is_array($item) && isset($item['type'], $item['message'])
        )) : [];
    }
}
