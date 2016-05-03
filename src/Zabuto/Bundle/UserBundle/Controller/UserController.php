<?php

namespace Zabuto\Bundle\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserController extends Controller
{

    public function listAction()
    {
        $em = $this->get('doctrine')->getManager();
        $users = $em->getRepository('ZabutoUserBundle:User')->getList();

        return $this->render('ZabutoUserBundle:User:list.html.twig', array('users' => $users));
    }

    public function lockedListAction()
    {
        $em = $this->get('doctrine')->getManager();
        $users = $em->getRepository('ZabutoUserBundle:User')->getLockedList();

        return $this->render('ZabutoUserBundle:User:locked-list.html.twig', array('users' => $users));
    }
    
    public function memberListAction()
    {
        $em = $this->get('doctrine')->getManager();
        $users = $em->getRepository('ZabutoUserBundle:User')->getList();

        return $this->render('ZabutoUserBundle:User:member-list.html.twig', array('users' => $users));
    }

    public function newAction()
    {
        $formFactory = $this->container->get('fos_user.registration.form.factory');
        $userManager = $this->container->get('fos_user.user_manager');

        $user = $userManager->createUser();
        $user->setEnabled(true);
        $user->setLastLogin(null);

        $form = $formFactory->createForm();
        $form->setData($user);

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $userManager->updateUser($user);
                $url = $this->container->get('router')->generate('zabuto_user_list');
                return new RedirectResponse($url);
            }
        }

        return $this->render('ZabutoUserBundle:User:new.html.twig', array('form' => $form->createView()));
    }

    public function editAction($id)
    {
        $formFactory = $this->container->get('fos_user.profile.form.factory');

        $em = $this->get('doctrine')->getManager();
        $user = $em->getRepository('ZabutoUserBundle:User')->findOneBy(array('id' => $id));

        $group = null;
        foreach ($user->getGroups() as $group) {
            break;
        }

        $form = $formFactory->createForm();
        $form->setData($user);

        //$form->get('groups')->setData(array($group));

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                if ($user->isEnabled() && $user->isLocked()) {
                    $user->setLocked(false);
                }

                $em->persist($user);
                $em->flush();

                if ($user->isLocked()) {
                    $url = $this->container->get('router')->generate('zabuto_user_locked_list');
                } else {
                    $url = $this->container->get('router')->generate('zabuto_user_list');
                }

                return new RedirectResponse($url);
            }
        }

        return $this->render('ZabutoUserBundle:User:edit.html.twig', array('id' => $id, 'username' => $user->getRealName(), 'locked' => $user->isLocked(), 'form' => $form->createView()));
    }

    public function editPasswordAction($id)
    {
        $formFactory = $this->container->get('fos_user.change_password.form.factory');
        $userManager = $this->container->get('fos_user.user_manager');

        $user = $userManager->findUserBy(array('id' => $id));
        $user->setEnabled(true);

        $form = $formFactory->createForm();
        $form->remove('current_password');

        $form->setData($user);

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $userManager->updateUser($user);
                $url = $this->container->get('router')->generate('zabuto_user_list');
                return new RedirectResponse($url);
            }
        }

        return $this->render('ZabutoUserBundle:User:edit-password.html.twig', array('id' => $id, 'username' => $user->getRealName(), 'form' => $form->createView()));
    }

    public function deleteAction($id)
    {
        $em = $this->get('doctrine')->getManager();
        $user = $em->getRepository('ZabutoUserBundle:User')->findOneBy(array('id' => $id));

        $user->setEnabled(false);
        $user->setLocked(true);
        $user->setRoles(array());

        $em->persist($user);
        $em->flush();

        $url = $this->container->get('router')->generate('zabuto_user_list');
        return new RedirectResponse($url);
    }

}
