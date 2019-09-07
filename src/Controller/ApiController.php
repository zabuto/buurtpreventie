<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\MeetingPoint;
use App\Entity\Result;
use App\Entity\Round;
use App\Service\CalendarService;
use App\Service\WalkService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ApiController
 *
 * @IsGranted("ROLE_USER")
 */
class ApiController extends AbstractController
{
    /**
     * @Route("/api/month", name="api_month")
     *
     * @param  Request         $request
     * @param  CalendarService $service
     * @return JsonResponse
     * @throws Exception
     */
    public function getMonth(Request $request, CalendarService $service)
    {
        $year = $request->get('year');
        $month = $request->get('month');
        if (!is_numeric($year) || !is_numeric($month)) {
            return new JsonResponse('Unable to retrieve data. Invalid year/month.', Response::HTTP_BAD_REQUEST);
        }

        $data = $service->getMonth((int)$year, (int)$month);

        return new JsonResponse($data);
    }

    /**
     * @Route("/api/round/{id}/walk", name="api_round_walk")
     *
     * @param  int                    $id
     * @param  WalkService            $service
     * @param  EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws Exception
     */
    public function roundWalk($id, WalkService $service, EntityManagerInterface $entityManager)
    {
        $round = $entityManager->getRepository(Round::class)->find($id);
        if (null === $round) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $service->walkRound($round, null);

        return new JsonResponse();
    }

    /**
     * @Route("/api/round/{id}/exit", name="api_round_exit")
     *
     * @param  int                    $id
     * @param  WalkService            $service
     * @param  EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws Exception
     */
    public function roundExit($id, WalkService $service, EntityManagerInterface $entityManager)
    {
        $round = $entityManager->getRepository(Round::class)->find($id);
        if (null === $round) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $service->exitRound($round);

        return new JsonResponse();
    }

    /**
     * @Route("/api/round/{id}/change", name="api_round_change")
     *
     * @param                         $id
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return JsonResponse
     * @throws Exception
     */
    public function roundChange($id, EntityManagerInterface $entityManager, Request $request)
    {
        $round = $entityManager->getRepository(Round::class)->find($id);
        if (null === $round) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $time = $request->get('time');
        if (!empty($time)) {
            $timeObj = new DateTime(sprintf('%s %s', $round->getDatetime()->format('Y-m-d'), $time));
            $round->setTime($timeObj);
        }

        $mpId = $request->get('meetingpoint');
        if (!empty($mpId)) {
            $mp = $entityManager->getRepository(MeetingPoint::class)->find($mpId);
            $round->setMeetingPoint($mp);
        }

        $entityManager->flush();

        return new JsonResponse([
            'id'           => $id,
            'datetime'     => $round->getDatetime()->format('c'),
            'meetingpoint' => (string)$round->getMeetingPoint(),
        ]);
    }

    /**
     * @Route("/api/round/{id}/result", name="api_round_result")
     *
     * @param  int                    $id
     * @param  WalkService            $service
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return JsonResponse
     * @throws Exception
     */
    public function roundResult($id, WalkService $service, EntityManagerInterface $entityManager, Request $request)
    {
        $round = $entityManager->getRepository(Round::class)->find($id);
        if (null === $round) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $result = $entityManager->getRepository(Result::class)->find($request->get('result'));
        if (null === $result) {
            return new JsonResponse('Result not found', Response::HTTP_NOT_FOUND);
        }

        $roundResult = $service->roundResult($round, $result, $request->get('memo'));

        return new JsonResponse($roundResult->getId());
    }

    /**
     * @Route("/api/round/{id}/comment", name="api_round_comment")
     *
     * @param  int                    $id
     * @param  WalkService            $service
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return JsonResponse
     * @throws Exception
     */
    public function roundComment($id, WalkService $service, EntityManagerInterface $entityManager, Request $request)
    {
        $round = $entityManager->getRepository(Round::class)->find($id);
        if (null === $round) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $memo = $request->get('memo');
        if (empty($memo)) {
            return new JsonResponse('Unable to add comment. Invalid memo.', Response::HTTP_BAD_REQUEST);
        }

        $comment = $service->addComment($round, strip_tags($memo));

        return new JsonResponse([
            'id'         => $comment->getId(),
            'created_by' => (string)$comment->getCreatedBy(),
            'created_at' => $comment->getCreatedAt()->format('c'),
        ]);
    }

    /**
     * @Route("/api/comment/{id}/delete", name="api_comment_delete")
     *
     * @param  int                    $id
     * @param  EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws Exception
     */
    public function commentDelete($id, EntityManagerInterface $entityManager)
    {
        $comment = $entityManager->getRepository(Comment::class)->find($id);
        if (null === $comment) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($comment);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
