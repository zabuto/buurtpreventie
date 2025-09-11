<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Round;
use App\Entity\User;
use App\Form\RoundMeetingPointType;
use App\Form\RoundTimeType;
use App\Form\RoundType;
use App\Repository\RoundRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class RoundController extends AbstractController
{
    #[Route('/round', name: 'round_list', methods: ['GET'])]
    public function list(RoundRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($this->isGranted('ROLE_COORDINATE') || $this->isGranted('ROLE_ANALYST')) {
            $user = null;
        }

        $list = $repo->getOrderedResults($user, 'DESC');

        return $this->render('round/list.html.twig', [
            'list' => $list,
        ]);
    }

    #[Route('/round/{id}', name: 'round_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, RoundRepository $repo): Response
    {
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        return $this->render('round/detail.html.twig', [
            'round' => $round,
        ]);
    }

    #[Route('/round/add', name: 'round_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MEMBER')]
    public function add(Request $request, RoundRepository $repo): RedirectResponse|Response
    {
        $round = new Round();
        $form = $this->createForm(RoundType::class, $round);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->create($round);

            return $this->redirectToRoute('round_detail', ['id' => $round->getId()]);
        }

        return $this->render('round/form.html.twig', [
            'id' => null,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/round/{id}/edit', name: 'round_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MEMBER')]
    public function edit(int $id, Request $request, RoundRepository $repo): RedirectResponse|Response
    {
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        $form = $this->createForm(RoundType::class, $round);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->update($round);

            return $this->redirectToRoute('round_detail', ['id' => $round->getId()]);
        }

        return $this->render('round/form.html.twig', [
            'id' => $id,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/round/{id}/delete', name: 'round_delete', requirements: ['id' => '\d+|placeholder'], methods: ['GET', 'POST', 'DELETE'])]
    #[IsGranted('ROLE_MEMBER')]
    public function delete(int $id, RoundRepository $repo): RedirectResponse|Response
    {
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        if ($this->isGranted('ROLE_COORDINATE') || $round->getCreatedBy() === $this->getUser()) {
            $repo->delete($round);

            return $this->redirectToRoute('round_list');
        }

        throw $this->createAccessDeniedException('exception.round.delete-denied');
    }

    #[Route('/round/{id}/modal/{type}', name: 'round_modal_type', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function modal(int $id, string $type, RoundRepository $repo): Response
    {
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        if ($type === 'time') {
            $form = $this->createForm(RoundTimeType::class, $round);
        } elseif ($type === 'meeting-point') {
            $form = $this->createForm(RoundMeetingPointType::class, $round);
        } else {
            throw $this->createNotFoundException('Type not valid');
        }

        return $this->render('round/change-form.html.twig', [
            'round' => $round,
            'form' => $form->createView(),
        ]);
    }
}
