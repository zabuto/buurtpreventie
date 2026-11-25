<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Mail;

use App\Shared\Domain\Branding;
use App\Shared\Domain\Notification;
use App\Shared\Infrastructure\Mail\ComposedMail;
use App\Shared\Infrastructure\Mail\LocalDateText;
use App\Shared\Infrastructure\Mail\MailComposer;
use App\Shared\Infrastructure\Mail\MailRenderer;
use DateTimeImmutable;
use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Name, rights and expiry of a new token on the member's name, never its secret, so a token they did not make stands
 * out. No link, for the reason in TokenLimitReachedMail.
 */
final readonly class TokenCreatedMail implements MailComposer
{
    public function __construct(
        private MailRenderer $renderer,
        private TranslatorInterface $translator,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return 'account.token_created';
    }

    #[Override]
    public function compose(Notification $notification, Branding $branding): ComposedMail
    {
        $locale = $branding->language();
        $expiresAt = new DateTimeImmutable((string) ($notification->parameters['expiresAt'] ?? 'now'));

        return $this->renderer->render(
            $branding,
            $this->translator->trans('account.token_created.subject', [], 'mail', $locale),
            'account/token_created',
            [
                'tokenName' => (string) ($notification->parameters['tokenName'] ?? ''),
                'scopes' => $this->scopes((string) ($notification->parameters['scopes'] ?? ''), $locale),
                'expiresOn' => LocalDateText::dateWithYear($expiresAt, $locale),
            ],
        );
    }

    /**
     * @return string such as "lezen, wijzigen en beheren"
     */
    private function scopes(string $scopes, string $locale): string
    {
        $words = \array_map(
            fn(string $scope): string => $this->translator->trans('account.token.scope.' . $scope, [], 'mail', $locale),
            \array_values(\array_filter(\explode(',', $scopes))),
        );
        if (\count($words) < 2) {
            return $words[0] ?? '';
        }

        $last = \array_pop($words);

        return \implode(', ', $words) . ' ' . $this->translator->trans('account.token.and', [], 'mail', $locale) . ' ' . $last;
    }
}
