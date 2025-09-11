<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Repository\MeetingPointRepository;
use App\Repository\ResultRepository;
use App\Repository\RoundRepository;
use App\Service\CalendarService;
use App\Service\WalkService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ApiController extends AbstractController
{
    #[Route('/api/month', name: 'api_month', methods: ['GET'])]
    public function getMonth(Request $request, CalendarService $service): JsonResponse
    {
        $year = $request->get('year');
        $month = $request->get('month');
        if (!is_numeric($year) || !is_numeric($month)) {
            return new JsonResponse('Unable to retrieve data. Invalid year/month.', Response::HTTP_BAD_REQUEST);
        }

        $data = $service->getMonth((int)$year, (int)$month);

        return new JsonResponse($data);
    }

    #[Route('/api/round/{id}/walk', name: 'api_round_walk', requirements: ['id' => '\d+|placeholder'], methods: ['GET', 'POST'])]
    public function roundWalk(int $id, RoundRepository $repo, WalkService $service): JsonResponse
    {
        $round = $repo->find($id);
        if (null === $round) {
            return new JsonResponse(sprintf('Round %s not found', $id), Response::HTTP_NOT_FOUND);
        }

        $service->walkRound($round, null);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/round/{id}/exit', name: 'api_round_exit', requirements: ['id' => '\d+|placeholder'], methods: ['GET', 'POST'])]
    public function roundExit(int $id, RoundRepository $repo, WalkService $service): JsonResponse
    {
        $round = $repo->find($id);
        if (null === $round) {
            return new JsonResponse(sprintf('Round %s not found', $id), Response::HTTP_NOT_FOUND);
        }

        $service->exitRound($round);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/round/{id}/change', name: 'api_round_change', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function roundChange(int $id, Request $request, RoundRepository $roundRepo, MeetingPointRepository $pointRepo): JsonResponse
    {
        $round = $roundRepo->find($id);
        if (null === $round) {
            return new JsonResponse(sprintf('Round %s not found', $id), Response::HTTP_NOT_FOUND);
        }

        $time = $request->get('time');
        if (!empty($time)) {
            if (null === $round->getDatetime()) {
                return new JsonResponse('Unable to update round. Unknown date.', Response::HTTP_PRECONDITION_FAILED);
            }

            $datetimeString = sprintf('%s %s', $round->getDatetime()->format('Y-m-d'), $time);
            $newDatetime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $datetimeString);
            $round->setDatetime($newDatetime);
        }

        $mpId = $request->get('meetingpoint');
        if (!empty($mpId)) {
            $round->setMeetingPoint($pointRepo->find($mpId));
        }

        $roundRepo->update($round);

        return new JsonResponse([
            'id' => $id,
            'datetime' => $round->getDatetime()?->format('c'),
            'meetingpoint' => (string)$round->getMeetingPoint(),
        ]);
    }

    #[Route('/api/round/{id}/result', name: 'api_round_result', requirements: ['id' => '\d+|placeholder'], methods: ['GET', 'POST'])]
    public function roundResult(int $id, Request $request, RoundRepository $roundRepo, ResultRepository $resultRepo, WalkService $service): JsonResponse
    {
        $round = $roundRepo->find($id);
        if (null === $round) {
            return new JsonResponse(sprintf('Round %s not found', $id), Response::HTTP_NOT_FOUND);
        }

        $result = $resultRepo->find($request->get('result'));
        if (null === $result) {
            return new JsonResponse('Result not found', Response::HTTP_NOT_FOUND);
        }

        $roundResult = $service->roundResult($round, $result, $request->get('memo'));

        return new JsonResponse($roundResult->getId());
    }

    #[Route('/api/round/{id}/comment', name: 'api_round_comment', requirements: ['id' => '\d+|placeholder'], methods: ['GET', 'POST'])]
    public function roundComment(int $id, Request $request, RoundRepository $repo, WalkService $service): JsonResponse
    {
        $round = $repo->find($id);
        if (null === $round) {
            return new JsonResponse(sprintf('Round %s not found', $id), Response::HTTP_NOT_FOUND);
        }

        $memo = $request->get('memo');
        if (empty($memo)) {
            return new JsonResponse('Unable to add comment. Invalid memo.', Response::HTTP_BAD_REQUEST);
        }

        $comment = $service->addComment($round, strip_tags($memo));

        return new JsonResponse([
            'id' => $comment->getId(),
            'created_by' => (string)$comment->getCreatedBy(),
            'created_at' => $comment->getCreatedAt()?->format('c'),
        ]);
    }

    #[Route('/api/comment/{id}/delete', name: 'api_comment_delete', requirements: ['id' => '\d+|placeholder'], methods: ['GET', 'POST', 'DELETE'])]
    public function commentDelete(int $id, CommentRepository $repo): JsonResponse
    {
        $comment = $repo->find($id);
        if (null === $comment) {
            return new JsonResponse(sprintf('Comment %s not found', $id), Response::HTTP_NOT_FOUND);
        }

        $repo->delete($comment);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
