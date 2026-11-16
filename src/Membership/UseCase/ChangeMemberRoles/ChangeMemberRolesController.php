<?php

declare(strict_types=1);

namespace App\Membership\UseCase\ChangeMemberRoles;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final readonly class ChangeMemberRolesController
{
    public function __construct(
        private ChangeMemberRolesHandler $handler,
    ) {
    }

    /**
     * The member comes from the path: the body needs no member id, and one it carries counts for nothing.
     */
    #[Route('/admin/members/{memberId}/roles', name: 'membership_change_member_roles', requirements: ['memberId' => Requirement::UUID], methods: ['PUT'])]
    public function __invoke(
        string $memberId,
        #[MapRequestPayload(serializationContext: ['default_constructor_arguments' => [ChangeMemberRoles::class => ['memberId' => '']]])]
        ChangeMemberRoles $input,
    ): Response {
        ($this->handler)(new ChangeMemberRoles($memberId, $input->roles));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
