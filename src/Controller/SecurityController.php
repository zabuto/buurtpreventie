<?php

namespace App\Controller;

use App\Exception\MailException;
use App\Exception\TokenInvalidException;
use App\Exception\UserInvalidException;
use App\Form\PasswordChangeType;
use App\Form\PasswordRepeatType;
use App\Interfaces\UserTokenInterface;
use App\Service\MailService;
use App\Service\UserService;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * SecurityController
 */
class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     *
     * @param  AuthenticationUtils $authenticationUtils
     * @return Response
     */
    public function login(AuthenticationUtils $authenticationUtils)
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * @Route("/account", name="account")
     * @IsGranted("ROLE_USER")
     *
     * @return Response
     */
    public function account()
    {
        $user = $this->getUser();

        return $this->render('security/account.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @Route("/password-change", name="password_change")
     * @IsGranted("ROLE_USER")
     *
     * @param  UserService         $userService
     * @param  TranslatorInterface $translator
     * @param  Request             $request
     * @return Response|RedirectResponse
     */
    public function password(UserService $userService, TranslatorInterface $translator, Request $request)
    {
        $user = $this->getUser();
        $form = $this->createForm(PasswordChangeType::class);

        try {
            $form->handleRequest($request);
            if ($form->isSubmitted()) {
                $current = $form->get('current')->getData();
                if (false === $userService->checkPassword($user, $current)) {
                    $error = new FormError($translator->trans('security.password-current-invalid'));
                    $form->get('current')->addError($error);
                }

                if ($form->isValid()) {
                    $new = $form->get('new')->getData();
                    $userService->updatePassword($user, $new);

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

    /**
     * @Route("/reset-password", name="password_reset")
     *
     * @param  UserService $userService
     * @param  MailService $mailService
     * @param  Request     $request
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function reset(UserService $userService, MailService $mailService, Request $request)
    {
        $assign = ['email' => null];
        if ($request->isMethod(Request::METHOD_POST)) {
            $email = $request->request->get('reset_email');
            $assign['email'] = $email;
            $user = $userService->resetPassword($email);
            if ($user instanceof UserTokenInterface) {
                try {
                    $mailService->resetTokenUser($user);
                } catch (MailException $e) {
                }
            }
        }

        return $this->render('security/reset.html.twig', $assign);
    }

    /**
     * @Route("/token/{token}", name="token")
     *
     * @param  string      $token
     * @param  UserService $userService
     * @param  Request     $request
     * @return Response|RedirectResponse
     */
    public function token($token, UserService $userService, Request $request)
    {
        try {
            $error = null;

            $user = $userService->getUserByToken($token);
            $user->setPassword('');

            $form = $this->createForm(PasswordRepeatType::class, $user);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $user = $form->getData();
                $userService->saveTokenUser($user, $user->getPassword());

                return $this->redirectToRoute('login');
            }
        } catch (TokenInvalidException $e) {
            $error = $e->getMessage();
            $form = null;
        }

        return $this->render('security/token.html.twig', [
            'token' => $token,
            'form'  => (null !== $form) ? $form->createView() : null,
            'error' => $error,
        ]);
    }
}
