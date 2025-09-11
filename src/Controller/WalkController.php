<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Round;
use App\Entity\RoundResult;
use App\Form\RoundResultType;
use App\Form\RoundType;
use App\Repository\RoundRepository;
use App\Repository\RoundResultRepository;
use App\Service\WalkService;
use DateTime;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WalkController extends AbstractController
{
    #[Route('/calendar', name: 'calendar', methods: ['GET'])]
    public function calendar(WalkService $service): Response
    {
        return $this->render('walk/calendar.html.twig', [
            'minimum' => $service->getWalkerMinimum(),
        ]);
    }

    #[Route('/calendar/day/{value}', name: 'calendar_day', methods: ['GET'])]
    public function calendarDay(string $value, RoundRepository $repo, WalkService $service): Response
    {
        $now = new DateTime();
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $list = $repo->getRoundsForDate($date);

        $round = new Round();
        $round->setDatetime($date);

        $form = $this->createForm(RoundType::class, $round, ['action' => $this->generateUrl('calendar_new_round')]);

        return $this->render('walk/calendar-day.html.twig', [
            'service' => $service,
            'date' => $date,
            'list' => $list,
            'allow_new' => ($date->format('Ymd') >= $now->format('Ymd')),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/calendar/new-round', name: 'calendar_new_round', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_WALK')]
    public function newRound(Request $request, WalkService $service): JsonResponse
    {
        $round = new Round();
        $form = $this->createForm(RoundType::class, $round);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $added = $service->addRound($round, $form->get('memo')->getData());

                return new JsonResponse($added?->getId(), Response::HTTP_CREATED);
            }

            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(null, Response::HTTP_NOT_MODIFIED);
    }

    #[Route('/walked', name: 'walked_list', methods: ['GET'])]
    #[IsGranted('ROLE_WALK')]
    public function walked(WalkService $service): Response
    {
        return $this->render('walk/walked.html.twig', [
            'service' => $service,
            'list' => $service->getWalked(),
        ]);
    }

    #[Route('/walk/{id}', name: 'walk_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, WalkService $service, RoundRepository $repo): Response
    {
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        return $this->render('walk/detail.html.twig', [
            'service' => $service,
            'round' => $round,
        ]);
    }

    #[Route('/walked/{id}/result', name: 'walked_result', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function result(int $id, Request $request, RoundRepository $roundRepo, RoundResultRepository $repo): RedirectResponse|Response
    {
        $round = $roundRepo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        $result = new RoundResult();
        $result->setRound($round);

        $form = $this->createForm(RoundResultType::class, $result);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->create($result);

            return $this->redirectToRoute('walk_detail', ['id' => $id]);
        }

        return $this->render('walk/result-form.html.twig', [
            'id' => $id,
            'form' => $form->createView(),
        ]);
    }
}
