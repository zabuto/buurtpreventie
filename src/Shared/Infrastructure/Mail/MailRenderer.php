<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Mail;

use App\Shared\Domain\Branding;
use Twig\Environment;

/**
 * Renders the HTML and text part of a mail from `templates/mail/<name>.html.twig` and `.txt.twig`, both of which
 * extend the layout of their kind. The layout gets the branding as `brand` and the language as `locale`.
 */
final readonly class MailRenderer
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $context the values the template of this mail needs
     */
    public function render(Branding $branding, string $subject, string $template, array $context): ComposedMail
    {
        $context = [
            ...$context,
            'subject' => $subject,
            'locale' => $branding->language(),
            'brand' => [
                'name' => $branding->associationName(),
                'color' => $branding->primaryColor(),
                'darkColor' => $branding->primaryDarkColor(),
                'hasLogo' => null !== $branding->mailLogo(),
                'canReply' => null !== $branding->contactEmail(),
            ],
        ];

        return new ComposedMail(
            $subject,
            $this->twig->render('mail/' . $template . '.html.twig', $context),
            $this->twig->render('mail/' . $template . '.txt.twig', $context),
        );
    }
}
