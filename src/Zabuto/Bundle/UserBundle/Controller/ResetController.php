<?php

namespace Zabuto\Bundle\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ResetController extends Controller
{
    public function forgotPasswordAction()
    {
        return $this->render('ZabutoUserBundle:Reset:forgot-password.html.twig', array());
    }
    
    public function resetPasswordAction()
    {
        $template = 'ZabutoUserBundle:Reset:forgot-password.html.twig';
        $error = '';
        $email = $this->getRequest()->get('email');
        
        if ($this->isValidEmail($email)) {
            $userManager = $this->container->get('fos_user.user_manager');
            $user = $userManager->findUserBy(array('email' => $email));
            if ($user) {
                $password = $this->generatePassword();
                $user->setPlainPassword($password);
                $userManager->updateUser($user);
                try {
                    //$mailManager = $this->get('zabuto_buurtpreventie.mailmanager');
                    //$mailManager->mailNieuwWachtwoord($user, $password);                    
                    $template = 'ZabutoUserBundle:Reset:password-sent.html.twig';
                } catch (Exception $e) {
                    $error = 'Er is een fout opgetreden, probeer het aub nogmaals';
                }
            } else {
                $error = 'Email adres is onbekend';
            }
        } else {
            $error = 'Email adres is ongeldig';
        }
        
        return $this->render($template, array('error' => $error));
    }
    
    private function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    private function generatePassword($length = 6)
    {
        $chars = 'AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz0123456789#@$!';
        $n = strlen($chars) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $index = mt_rand(0, $n);
            $password .= $chars[$index];
        }
        return $password;
    }
}