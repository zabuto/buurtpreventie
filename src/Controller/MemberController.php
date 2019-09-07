<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\MemberType;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * MemberController
 *
 * @IsGranted("ROLE_MEMBER")
 */
class MemberController extends AbstractController
{
    /**
     * @Route("/member", name="member_list")
     *
     * @param  UserService            $userService
     * @param  EntityManagerInterface $entityManager
     * @return Response
     */
    public function list(UserService $userService, EntityManagerInterface $entityManager)
    {
        $entityManager->getFilters()->enable('soft_delete');

        /** @var UserRepository $repo */
        $repo = $entityManager->getRepository(User::class);
        $list = $repo->getActiveUsersForRoles($userService->getMemberRoles());

        return $this->render('member/list.html.twig', [
            'list' => $list,
        ]);
    }

    /**
     * @Route("/member/{id}/edit", name="member_edit")
     * @IsGranted("ROLE_COORDINATE")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function edit($id, EntityManagerInterface $entityManager, Request $request)
    {
        $repo = $entityManager->getRepository(User::class);
        $user = $repo->find($id);
        if (null === $user) {
            $this->createNotFoundException('exception.member.not-found');
        }

        $form = $this->createForm(MemberType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('member_list');
        }

        return $this->render('member/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
