<?php

namespace Zabuto\Bundle\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Zabuto\Bundle\UserBundle\Entity\User;

class ProfileController extends Controller
{

    public function indexAction()
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        if (!is_object($user) || !$user instanceof User) {
            throw new AccessDeniedException('Geen toegang');
        }

        $userManager = $this->container->get('fos_user.user_manager');

        $formFactory = $this->container->get('fos_user.change_password.form.factory');

        $form = $formFactory->createForm();
        $form->setData($user);

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $userManager->updateUser($user);
                $this->get('session')->getFlashBag()->add('zabuto-user-profile', 'Wachtwoord is gewijzigd');
                return $this->redirect($this->generateUrl($this->getRequest()->get('_route')));
            }
        }

        return $this->render('ZabutoUserBundle:Profile:index.html.twig', array('user' => $user, 'form' => $form->createView()));
    }

}
