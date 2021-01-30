<?php

namespace App\Controller;

use App\Entity\Result;
use App\Form\ResultType;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ConfigResultController
 *
 * @IsGranted("ROLE_ADMIN")
 */
class ConfigResultController extends AbstractController
{
    /**
     * @Route("/admin/result", name="config_result_list")
     *
     * @param  EntityManagerInterface $entityManager
     * @return Response
     */
    public function list(EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(Result::class);
        $list = $repo->findBy([], ['description' => 'ASC']);

        return $this->render('config/result_list.html.twig', [
            'list' => $list,
        ]);
    }

    /**
     * @Route("/admin/result/add", name="config_result_add")
     *
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     */
    public function add(EntityManagerInterface $entityManager, Request $request)
    {
        $form = $this->createForm(ResultType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $result = $form->getData();
            $entityManager->persist($result);
            $entityManager->flush();

            return $this->redirectToRoute('config_result_list');
        }

        return $this->render('config/result_form.html.twig', [
            'id'   => null,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/result/{id}/edit", name="config_result_edit")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function edit($id, EntityManagerInterface $entityManager, Request $request)
    {
        $repo = $entityManager->getRepository(Result::class);
        $result = $repo->find($id);
        if (null === $result) {
            throw $this->createNotFoundException('exception.result.not-found');
        }

        $form = $this->createForm(ResultType::class, $result);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('config_result_list');
        }

        return $this->render('config/result_form.html.twig', [
            'id'      => $id,
            'deleted' => $result->isDeleted(),
            'form'    => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/result/{id}/delete", name="config_result_delete")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response|RedirectResponse
     */
    public function delete($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(Result::class);
        $result = $repo->find($id);
        if (null === $result) {
            throw $this->createNotFoundException('exception.result.not-found');
        }

        $entityManager->remove($result);
        $entityManager->flush();

        return $this->redirectToRoute('config_result_list');
    }

    /**
     * @Route("/admin/result/{id}/restore", name="config_result_restore")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function restore($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(Result::class);
        $result = $repo->find($id);
        if (null === $result) {
            throw $this->createNotFoundException('exception.result.not-found');
        }

        $result->restore();
        $entityManager->flush();

        return $this->redirectToRoute('config_result_list');
    }
}
