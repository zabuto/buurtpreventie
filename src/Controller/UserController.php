<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\MailException;
use App\Exception\UserInvalidException;
use App\Form\UserAddType;
use App\Form\UserEditType;
use App\Interfaces\UserTokenInterface;
use App\Service\MailService;
use App\Service\UserService;
use App\Service\WalkService;
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
            /** @var User $user */
            $user = $form->getData();

            $userService->checkNewUser($user);
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
     * @param  WalkService            $walkService
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     * @throws UserInvalidException
     */
    public function edit($id, UserService $userService, WalkService $walkService, EntityManagerInterface $entityManager, Request $request)
    {
        $repo = $entityManager->getRepository(User::class);
        $user = $repo->find($id);
        if (null === $user) {
            throw $this->createNotFoundException('exception.user.not-found');
        }

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();
            $userService->saveUser($user);

            if (false === $user->isActive()) {
                $walkService->walkerRemoveFromFutureRounds($user);
            }

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
     * @param  WalkService            $walkService
     * @param  EntityManagerInterface $entityManager
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function delete($id, WalkService $walkService, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(User::class);

        /** @var User $user */
        $user = $repo->find($id);
        if (null === $user) {
            throw $this->createNotFoundException('exception.user.not-found');
        }

        $user->setPermitted(false);
        $user->setAddress(null);
        $user->setPhone(null);
        $user->setMobile(null);
        $user->setPassword('');
        $user->setActive(false);

        $walkService->walkerRemoveFromFutureRounds($user);

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
            throw $this->createNotFoundException('exception.user.not-found');
        }

        $user->restore();
        $entityManager->flush();

        return $this->redirectToRoute('user_list');
    }

    /**
     * @Route("/admin/user/{id}/token", name="user_token")s
     *
     * @param  integer                $id
     * @param  UserService            $userService
     * @param  MailService            $mailService
     * @param  EntityManagerInterface $entityManager
     * @return Response
     * @throws NotFoundHttpException
     */
    public function token($id, UserService $userService, MailService $mailService, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(User::class);
        $user = $repo->find($id);
        if (null === $user) {
            throw $this->createNotFoundException('exception.user.not-found');
        }

        $assign = ['user' => $user, 'token' => null];
        if ($user instanceof UserTokenInterface) {
            $userService->generateToken($user, 24);
            $entityManager->flush();

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

        return $this->render('user/token.html.twig', $assign);
    }
}
