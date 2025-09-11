<?php declare(strict_types=1);

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use App\Exception\MailException;
use App\Exception\UserInvalidException;
use App\Form\UserAddType;
use App\Form\UserEditType;
use App\Repository\UserRepository;
use App\Service\MailService;
use App\Service\WalkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/admin/user', name: 'user_list', methods: ['GET'])]
    public function list(UserRepository $repo): Response
    {
        $list = $repo->findBy([], ['name' => 'ASC']);

        return $this->render('user/list.html.twig', [
            'list' => $list,
        ]);
    }

    #[Route('/admin/user/add', name: 'user_add', methods: ['GET', 'POST'])]
    public function add(Request $request, MailService $mailService, ObjectMapperInterface $mapper, UserRepository $repo, ValidatorInterface $validator): Response
    {
        $user = new User();
        $form = $this->createForm(UserAddType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $count = $repo->getUserCountForEmail($user->getEmail());
            if ($count > 0) {
                throw new UserInvalidException('user.email-found');
            }

            $user->generateToken(12);
            $errors = $validator->validate($user);
            if (count($errors) > 0) {
                throw new UserInvalidException('exception.user.invalid');
            }

            $repo->create($user);

            try {
                $userDto = $mapper->map($user, UserDto::class);
                $mailService->welcomeNewUser($userDto);
                $warning = null;
            } catch (MailException $e) {
                $warning = $e->getMessage();
            }

            return $this->render('user/added.html.twig', [
                'warning' => $warning,
                'user' => $user,
                'token' => [
                    'hash' => $user->getToken(),
                    'date' => $user->getTokenValidUntil(),
                ]
            ]);
        }

        return $this->render('user/form.html.twig', [
            'id' => null,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/user/{id}/edit', name: 'user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, UserRepository $repo, WalkService $walkService): RedirectResponse|Response
    {
        $user = $repo->find($id);
        if (null === $user) {
            throw $this->createNotFoundException('exception.user.not-found');
        }

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->update($user);
            if (false === $user->isActive()) {
                $walkService->walkerRemoveFromFutureRounds($user);
            }

            return $this->redirectToRoute('user_list');
        }

        return $this->render('user/form.html.twig', [
            'id' => $id,
            'deleted' => $user->isDeleted(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/user/{id}/delete', name: 'user_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST', 'DELETE'])]
    public function delete(int $id, UserRepository $repo, WalkService $walkService): RedirectResponse|Response
    {
        $user = $repo->find($id);
        if (null === $user) {
            throw $this->createNotFoundException('exception.user.not-found');
        }

        $user->erasePersonalInformation();
        $walkService->walkerRemoveFromFutureRounds($user);
        $repo->delete($user);

        return $this->redirectToRoute('user_list');
    }

    #[Route('/admin/user/{id}/restore', name: 'user_restore', requirements: ['id' => '\d+'], methods: ['GET', 'POST', 'PUT'])]
    public function restore(int $id, UserRepository $repo): RedirectResponse|Response
    {
        $user = $repo->find($id);
        if (null === $user) {
            throw $this->createNotFoundException('exception.user.not-found');
        }

        $repo->restore($user);

        return $this->redirectToRoute('user_list');
    }

    #[Route('/admin/user/{id}/token', name: 'user_token', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function token(int $id, MailService $mailService, ObjectMapperInterface $mapper, UserRepository $repo): Response
    {
        $user = $repo->find($id);
        if (null === $user) {
            throw $this->createNotFoundException('exception.user.not-found');
        }

        $user->generateToken(24);
        $repo->update($user);

        try {
            $userDto = $mapper->map($user, UserDto::class);
            $mailService->welcomeNewUser($userDto);
            $warning = null;
        } catch (MailException $e) {
            $warning = $e->getMessage();
        }

        return $this->render('user/token.html.twig', [
            'warning' => $warning,
            'user' => $user,
            'token' => [
                'hash' => $user->getToken(),
                'date' => $user->getTokenValidUntil(),
            ]
        ]);
    }
}
