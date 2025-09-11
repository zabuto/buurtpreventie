<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Result;
use App\Form\ResultType;
use App\Repository\ResultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ConfigResultController extends AbstractController
{
    #[Route('/admin/result', name: 'config_result_list', methods: ['GET'])]
    public function list(ResultRepository $repo): Response
    {
        $list = $repo->findBy([], ['description' => 'ASC']);

        return $this->render('config/result_list.html.twig', [
            'list' => $list,
        ]);
    }

    #[Route('/admin/result/add', name: 'config_result_add', methods: ['GET', 'POST'])]
    public function add(Request $request, ResultRepository $repo): RedirectResponse|Response
    {
        $result = new Result();
        $form = $this->createForm(ResultType::class, $result);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->create($result);

            return $this->redirectToRoute('config_result_list');
        }

        return $this->render('config/result_form.html.twig', [
            'id' => null,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/result/{id}/edit', name: 'config_result_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, ResultRepository $repo): RedirectResponse|Response
    {
        $result = $repo->find($id);
        if (null === $result) {
            throw $this->createNotFoundException('exception.result.not-found');
        }

        $form = $this->createForm(ResultType::class, $result);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->update($result);

            return $this->redirectToRoute('config_result_list');
        }

        return $this->render('config/result_form.html.twig', [
            'id' => $id,
            'deleted' => $result->isDeleted(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/result/{id}/delete', name: 'config_result_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST', 'DELETE'])]
    public function delete(int $id, ResultRepository $repo): RedirectResponse|Response
    {
        $result = $repo->find($id);
        if (null === $result) {
            throw $this->createNotFoundException('exception.result.not-found');
        }

        $repo->delete($result);

        return $this->redirectToRoute('config_result_list');
    }

    #[Route('/admin/result/{id}/restore', name: 'config_result_restore', requirements: ['id' => '\d+'], methods: ['GET', 'POST', 'PUT'])]
    public function restore(int $id, ResultRepository $repo): RedirectResponse|Response
    {
        $result = $repo->find($id);
        if (null === $result) {
            throw $this->createNotFoundException('exception.result.not-found');
        }

        $repo->restore($result);

        return $this->redirectToRoute('config_result_list');
    }
}
