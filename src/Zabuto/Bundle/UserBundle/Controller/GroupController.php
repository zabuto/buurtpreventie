<?php

namespace Zabuto\Bundle\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class GroupController extends Controller
{

    public function listAction()
    {
        $em = $this->get('doctrine')->getManager();
        $groups = $em->getRepository('ZabutoUserBundle:Group')->getList();
        return $this->render('ZabutoUserBundle:Group:list.html.twig', array('groups' => $groups));
    }

    public function newAction()
    {
        $formFactory = $this->container->get('fos_user.group.form.factory');
        $groupManager = $this->container->get('fos_user.group_manager');

        $group = $groupManager->createGroup('');
        $form = $formFactory->createForm();
        $form->setData($group);

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $groupManager->updateGroup($group);
                $url = $this->container->get('router')->generate('zabuto_usergroup_list');
                return new RedirectResponse($url);
            }
        }

        return $this->render('ZabutoUserBundle:Group:new.html.twig', array('form' => $form->createView()));
    }

    public function editAction($id)
    {
        $formFactory = $this->container->get('fos_user.group.form.factory');

        $em = $this->get('doctrine')->getManager();
        $group = $em->getRepository('ZabutoUserBundle:Group')->findOneBy(array('id' => $id));

        $form = $formFactory->createForm();
        $form->setData($group);

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $em->persist($group);
                $em->flush();

                $url = $this->container->get('router')->generate('zabuto_usergroup_list');
                return new RedirectResponse($url);
            }
        }

        return $this->render('ZabutoUserBundle:Group:edit.html.twig', array('id' => $id, 'form' => $form->createView()));
    }

    public function deleteAction($id)
    {
        $em = $this->get('doctrine')->getManager();
        $group = $em->getRepository('ZabutoUserBundle:Group')->findOneBy(array('id' => $id));
        $this->container->get('fos_user.group_manager')->deleteGroup($group);

        $url = $this->container->get('router')->generate('zabuto_usergroup_list');
        return new RedirectResponse($url);
    }

}
