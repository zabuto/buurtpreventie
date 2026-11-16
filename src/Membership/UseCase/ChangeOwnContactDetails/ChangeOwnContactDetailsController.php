<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeOwnContactDetails;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ChangeOwnContactDetailsController
{
    public function __construct(
        private ChangeOwnContactDetailsHandler $handler,
    ) {
    }

    /**
     * Always for the member acting: a member id in the body counts for nothing.
     */
    #[Route('/me', name: 'membership_change_own_contact_details', methods: ['PATCH'])]
    #[OA\Response(response: 204, description: 'Changed.')]
    public function __invoke(#[MapRequestPayload] ChangeOwnContactDetails $input): Response
    {
        ($this->handler)(new ChangeOwnContactDetails(
            $input->phone,
            $input->address,
            $input->consentContactShare,
            $input->notifyOpenRounds,
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
