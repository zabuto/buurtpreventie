<?php

namespace App\Controller;

use App\Entity\Round;
use App\Entity\RoundResult;
use App\Form\RoundResultType;
use App\Form\RoundType;
use App\Service\WalkService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * WalkController
 *
 * @IsGranted("ROLE_USER")
 */
class WalkController extends AbstractController
{
    /**
     * @Route("/calendar", name="calendar")
     *
     * @param  WalkService $service
     * @return Response
     */
    public function calendar(WalkService $service)
    {
        return $this->render('walk/calendar.html.twig', [
            'minimum' => $service->getWalkerMinimum(),
        ]);
    }

    /**
     * @Route("/calendar/day/{value}", name="calendar_day")
     *
     * @param  string                 $value
     * @param  WalkService            $service
     * @param  EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function calendarDay($value, WalkService $service, EntityManagerInterface $entityManager)
    {
        $now = new DateTime();
        $date = new DateTime($value);

        $entityManager->getFilters()->enable('soft_delete');
        $repo = $entityManager->getRepository(Round::class);
        $list = $repo->getRoundsForDate($date);

        $round = new Round();
        $round->setDate($date);

        $form = $this->createForm(RoundType::class, $round, ['action' => $this->generateUrl('calendar_new_round')]);

        return $this->render('walk/calendar-day.html.twig', [
            'service'   => $service,
            'date'      => $date,
            'list'      => $list,
            'allow_new' => ($date->format('Ymd') >= $now->format('Ymd')),
            'form'      => $form->createView(),
        ]);
    }

    /**
     * @Route("/calendar/new-round", name="calendar_new_round")
     * @IsGranted("ROLE_WALK")
     *
     * @param  Request     $request
     * @param  WalkService $service
     * @return JsonResponse
     */
    public function newRound(Request $request, WalkService $service)
    {
        $round = new Round();
        $form = $this->createForm(RoundType::class, $round);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $added = $service->addRound($round, $form->get('memo')->getData());

                return new JsonResponse($added->getId(), Response::HTTP_CREATED);
            } else {
                return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
            }
        }

        return new JsonResponse(null, Response::HTTP_NOT_MODIFIED);
    }

    /**
     * @Route("/walked", name="walked_list")
     * @IsGranted("ROLE_WALK")
     *
     * @param  WalkService $service
     * @return Response
     * @throws Exception
     */
    public function walked(WalkService $service)
    {
        return $this->render('walk/walked.html.twig', [
            'service' => $service,
            'list'    => $service->getWalked(),
        ]);
    }

    /**
     * @Route("/walk/{id}", name="walk_detail", requirements={"id"="\d+"})
     *
     * @param  integer                $id
     * @param  WalkService            $service
     * @param  EntityManagerInterface $entityManager
     * @return Response
     * @throws NotFoundHttpException
     */
    public function detail($id, WalkService $service, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(Round::class);
        $round = $repo->find($id);
        if (null === $round) {
            $this->createNotFoundException('exception.round.not-found');
        }

        return $this->render('walk/detail.html.twig', [
            'service' => $service,
            'round'   => $round,
        ]);
    }

    /**
     * @Route("/walked/{id}/result", name="walked_result", requirements={"id"="\d+"})
     * @IsGranted("ROLE_WALK")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response
     * @throws NotFoundHttpException
     */
    public function result($id, EntityManagerInterface $entityManager, Request $request)
    {
        $repo = $entityManager->getRepository(Round::class);
        $round = $repo->find($id);
        if (null === $round) {
            $this->createNotFoundException('exception.round.not-found');
        }

        $result = new RoundResult();
        $result->setRound($round);

        $form = $this->createForm(RoundResultType::class, $result);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('walked_list');
        }

        return $this->render('walk/result-form.html.twig', [
            'id'   => $id,
            'form' => $form->createView(),
        ]);
    }
}
