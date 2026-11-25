<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeMember;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class ChangeMemberController
{
    public function __construct(
        private ChangeMemberHandler $handler,
    ) {
    }

    /**
     * The member comes from the path: the body needs no member id, and one it carries counts for nothing.
     */
    #[Route('/admin/members/{memberId}', name: 'membership_change_member', requirements: ['memberId' => Requirement::UUID], methods: ['PATCH'])]
    #[OA\Response(response: 204, description: 'Changed.')]
    public function __invoke(
        string $memberId,
        #[MapRequestPayload(serializationContext: ['default_constructor_arguments' => [ChangeMember::class => ['memberId' => '']]])]
        ChangeMember $input,
    ): Response {
        ($this->handler)(new ChangeMember(
            $memberId,
            $input->email,
            $input->name,
            $input->displayName,
            $input->phone,
            $input->address,
            $input->certified,
            $input->mcpAllowed,
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
