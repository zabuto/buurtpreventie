<?php

namespace App\Controller;

use App\Entity\RoundWalker;
use App\Entity\User;
use App\Exception\MailException;
use App\Exception\UserInvalidException;
use App\Form\UserAddType;
use App\Form\UserEditType;
use App\Interfaces\UserTokenInterface;
use App\Service\MailService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * UserController
 *
 * @IsGranted("ROLE_ADMIN")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/admin/user", name="user_list")
     *
     * @param  EntityManagerInterface $entityManager
     * @return Response
     */
    public function list(EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(User::class);
        $list = $repo->findBy([], ['name' => 'ASC']);

        return $this->render('user/list.html.twig', [
            'list' => $list,
        ]);
    }

    /**
     * @Route("/admin/user/add", name="user_add")
     *
     * @param  UserService $userService
     * @param  MailService $mailService
     * @param  Request     $request
     * @return Response
     * @throws UserInvalidException
     * @throws Exception
     */
    public function add(UserService $userService, MailService $mailService, Request $request)
    {
        $user = $userService->initUser();
        $form = $this->createForm(UserAddType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $userService->saveUser($user);
            $assign = ['user' => $user, 'token' => null];

            if ($user instanceof UserTokenInterface) {
                $assign['token'] = [
                    'hash' => $user->getToken(),
                    'date' => $user->getTokenValidUntil(),
                ];

                try {
                    $mailService->activateNewUser($user);
                } catch (MailException $e) {
                    $assign['warning'] = $e->getMessage();
                }
            }

            return $this->render('user/added.html.twig', $assign);
        }

        return $this->render('user/form.html.twig', [
            'id'   => null,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/user/{id}/edit", name="user_edit")
     *
     * @param  integer                $id
     * @param  UserService            $userService
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     * @throws UserInvalidException
     */
    public function edit($id, UserService $userService, EntityManagerInterface $entityManager, Request $request)
    {
        $repo = $entityManager->getRepository(User::class);
        $user = $repo->find($id);
        if (null === $user) {
            $this->createNotFoundException('exception.user.not-found');
        }

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $userService->saveUser($user);

            return $this->redirectToRoute('user_list');
        }

        return $this->render('user/form.html.twig', [
            'id'      => $id,
            'deleted' => $user->isDeleted(),
            'form'    => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/user/{id}/delete", name="user_delete")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function delete($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(User::class);

        /** @var User $user */
        $user = $repo->find($id);
        if (null === $user) {
            $this->createNotFoundException('exception.user.not-found');
        }

        $user->setPermitted(false);
        $user->setAddress(null);
        $user->setPhone(null);
        $user->setMobile(null);
        $user->setPassword('');
        $user->setActive(false);

        $walking = $entityManager->getRepository(RoundWalker::class)->getFutureForWalker($user);
        foreach ($walking as $walk) {
            $walk->doHardDelete();
            $entityManager->remove($walk);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return $this->redirectToRoute('user_list');
    }

    /**
     * @Route("/admin/user/{id}/restore", name="user_restore")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function restore($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(User::class);
        $user = $repo->find($id);
        if (null === $user) {
            $this->createNotFoundException('exception.user.not-found');
        }

        $user->restore();
        $entityManager->flush();

        return $this->redirectToRoute('user_list');
    }
}
