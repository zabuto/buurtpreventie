<?php

namespace App\Controller;

use App\Entity\Result;
use App\Entity\Round;
use App\Form\RoundResultType;
use App\Service\WalkService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ResultController
 *
 * @IsGranted("ROLE_USER")
 */
class ResultController extends AbstractController
{
    /**
     * @Route("/result", name="result_list")
     *
     * @param  WalkService $walkService
     * @return Response
     */
    public function list(WalkService $walkService)
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_COORDINATE') || $this->isGranted('ROLE_ANALYST')) {
            $user = null;
        }

        $resultModel = $walkService->getResults($user);

        return $this->render('result/list.html.twig', [
            'service' => $walkService,
            'list'    => $resultModel->getList(),
            'metrics' => $resultModel->getMetrics(),
        ]);
    }

    /**
     * @Route("/result/round/{id}/modal", name="result_round_modal", requirements={"id"="\d+"})
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response
     * @throws NotFoundHttpException
     */
    public function modal($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(Round::class);
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        $results = $entityManager->getRepository(Result::class)->findAll();

        $form = $this->createForm(RoundResultType::class);

        return $this->render('result/modal.html.twig', [
            'round'   => $round,
            'results' => $results,
            'form'    => $form->createView(),
        ]);
    }

    /**
     * @param  Round                  $round
     * @param  EntityManagerInterface $entityManager
     * @return Response
     */
    public function inline(Round $round, EntityManagerInterface $entityManager)
    {
        $results = $entityManager->getRepository(Result::class)->findAll();

        $form = $this->createForm(RoundResultType::class);

        return $this->render('result/inline.html.twig', [
            'round'   => $round,
            'results' => $results,
            'form'    => $form->createView(),
        ]);
    }
}
