<?php declare(strict_types=1);

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use App\Exception\MailException;
use App\Exception\UserInvalidException;
use App\Form\PasswordChangeType;
use App\Form\PasswordRepeatType;
use App\Form\PermittedChangeType;
use App\Repository\UserRepository;
use App\Service\MailService;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new RuntimeException('You must activate the logout in your security firewall configuration.');
    }

    #[Route('/account', name: 'account', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function account(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('security/account.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/permitted-change', name: 'permitted_change', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function permitted(Request $request, UserRepository $repo): RedirectResponse|Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(PermittedChangeType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->update($user);

            return $this->redirectToRoute('account');
        }

        return $this->render('security/permitted-change.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/password-change', name: 'password_change', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function password(Request $request, TranslatorInterface $translator, UserRepository $repo, UserPasswordHasherInterface $passwordHasher): RedirectResponse|Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(PasswordChangeType::class);

        try {
            $form->handleRequest($request);
            if ($form->isSubmitted()) {
                $currentPassword = $form->get('current')->getData();
                if (false === $passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $error = new FormError($translator->trans('security.password-current-invalid'));
                    $form->get('current')->addError($error);
                }

                if ($form->isValid()) {
                    $plaintextNewPassword = $form->get('new')->getData();
                    $hashedNewPassword = $passwordHasher->hashPassword($user, $plaintextNewPassword);
                    $user->setPassword($hashedNewPassword);
                    $repo->update($user);

                    return $this->redirectToRoute('account');
                }
            }
        } catch (UserInvalidException $e) {
            $error = new FormError($e->getMessage());
            $form->addError($error);
        }

        return $this->render('security/password-change.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password', name: 'password_reset', methods: ['GET', 'POST'])]
    public function reset(Request $request, MailService $mailService, ObjectMapperInterface $mapper, UserRepository $repo): RedirectResponse|Response
    {
        $email = null;
        $warning = null;

        if ($request->isMethod(Request::METHOD_POST)) {
            $email = (string)$request->request->get('reset_email');
            $user = $repo->findOneByEmail($email);
            if (null !== $user) {
                $user->generateToken();
                $repo->update($user);

                try {
                    $userDto = $mapper->map($user, UserDto::class);
                    $mailService->resetTokenUser($userDto);
                } catch (MailException $e) {
                    $warning = $e->getMessage();
                }
            }
        }

        return $this->render('security/reset.html.twig', ['email' => $email, 'warning' => $warning]);
    }

    #[Route('/token/{token}', name: 'token', methods: ['GET', 'POST'])]
    public function token(string $token, Request $request, TranslatorInterface $translator, UserRepository $repo, UserPasswordHasherInterface $passwordHasher): RedirectResponse|Response
    {
        $form = null;
        $error = null;

        $user = $repo->findOneByToken($token);
        if (null !== $user && $user->isTokenValid()) {
            $user->setPassword('');

            $form = $this->createForm(PasswordRepeatType::class, $user);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $plaintextPassword = $form->get('password')->getData();
                $hashedPassword = $passwordHasher->hashPassword($user, $plaintextPassword);
                $user->setPassword($hashedPassword);
                $user->resetToken();
                $repo->update($user);

                return $this->redirectToRoute('login');
            }
        } else {
            $error = (null === $user) ? $translator->trans('exception.token.invalid') : $translator->trans('exception.token.expired');
        }

        return $this->render('security/token.html.twig', [
            'token' => $token,
            'form' => (null !== $form) ? $form->createView() : null,
            'error' => $error,
        ]);
    }
}
