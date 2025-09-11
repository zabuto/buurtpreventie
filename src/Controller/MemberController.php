<?php declare(strict_types=1);

namespace App\Controller;

use App\Form\MemberType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
class MemberController extends AbstractController
{
    #[Route('/member', name: 'member_list', methods: ['GET'])]
    public function list(UserRepository $repo): Response
    {
        return $this->render('member/list.html.twig', [
            'list' => $repo->findActiveMembers(),
        ]);
    }

    #[Route('/member/{id}/edit', name: 'member_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_COORDINATE')]
    public function edit(int $id, Request $request, UserRepository $repo): RedirectResponse|Response
    {
        $user = $repo->find($id);
        if (null === $user) {
            throw $this->createNotFoundException('exception.member.not-found');
        }

        $form = $this->createForm(MemberType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->update($user);

            return $this->redirectToRoute('member_list');
        }

        return $this->render('member/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
