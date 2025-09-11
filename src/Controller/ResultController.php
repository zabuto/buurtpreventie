<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Round;
use App\Entity\User;
use App\Form\RoundResultType;
use App\Repository\ResultRepository;
use App\Repository\RoundRepository;
use App\Service\WalkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ResultController extends AbstractController
{
    #[Route('/result', name: 'result_list', methods: ['GET'])]
    public function list(WalkService $walkService): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($this->isGranted('ROLE_COORDINATE') || $this->isGranted('ROLE_ANALYST')) {
            $user = null;
        }

        $resultModel = $walkService->getResults($user);

        return $this->render('result/list.html.twig', [
            'service' => $walkService,
            'list' => $resultModel->list,
            'metrics' => $resultModel->metrics,
        ]);
    }

    #[Route('/result/round/{id}/modal', name: 'result_round_modal', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function modal(int $id, RoundRepository $roundRepo, ResultRepository $resultRepo): Response
    {
        $round = $roundRepo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        $results = $resultRepo->findAll();
        $form = $this->createForm(RoundResultType::class);

        return $this->render('result/modal.html.twig', [
            'round' => $round,
            'results' => $results,
            'form' => $form->createView(),
        ]);
    }

    public function inline(Round $round, ResultRepository $resultRepo): Response
    {
        $results = $resultRepo->findAll();
        $form = $this->createForm(RoundResultType::class);

        return $this->render('result/inline.html.twig', [
            'round' => $round,
            'results' => $results,
            'form' => $form->createView(),
        ]);
    }
}
