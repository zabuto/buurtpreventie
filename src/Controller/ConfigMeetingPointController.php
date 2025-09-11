<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\MeetingPoint;
use App\Form\MeetingPointType;
use App\Repository\MeetingPointRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ConfigMeetingPointController extends AbstractController
{
    #[Route('/admin/meeting-point', name: 'config_meetingpoint_list', methods: ['GET'])]
    public function list(MeetingPointRepository $repo): Response
    {
        $list = $repo->findBy([], ['description' => 'ASC']);

        return $this->render('config/meetingpoint_list.html.twig', [
            'list' => $list,
        ]);
    }

    #[Route('/admin/meeting-point/add', name: 'config_meetingpoint_add', methods: ['GET', 'POST'])]
    public function add(Request $request, MeetingPointRepository $repo): RedirectResponse|Response
    {
        $meetingpoint = new MeetingPoint();
        $form = $this->createForm(MeetingPointType::class, $meetingpoint);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->create($meetingpoint);

            return $this->redirectToRoute('config_meetingpoint_list');
        }

        return $this->render('config/meetingpoint_form.html.twig', [
            'id' => null,
            'point' => null,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/meeting-point/{id}/edit', name: 'config_meetingpoint_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, MeetingPointRepository $repo): RedirectResponse|Response
    {
        $meetingpoint = $repo->find($id);
        if (null === $meetingpoint) {
            throw $this->createNotFoundException('exception.meetingpoint.not-found');
        }

        $form = $this->createForm(MeetingPointType::class, $meetingpoint);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $repo->update($meetingpoint);

            return $this->redirectToRoute('config_meetingpoint_list');
        }

        return $this->render('config/meetingpoint_form.html.twig', [
            'id' => $id,
            'point' => $meetingpoint->getLocation(),
            'deleted' => $meetingpoint->isDeleted(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/meeting-point/{id}/delete', name: 'config_meetingpoint_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST', 'DELETE'])]
    public function delete(int $id, MeetingPointRepository $repo): RedirectResponse|Response
    {
        $meetingpoint = $repo->find($id);
        if (null === $meetingpoint) {
            throw $this->createNotFoundException('exception.meetingpoint.not-found');
        }

        $repo->delete($meetingpoint);

        return $this->redirectToRoute('config_meetingpoint_list');
    }

    #[Route('/admin/meeting-point/{id}/restore', name: 'config_meetingpoint_restore', requirements: ['id' => '\d+'], methods: ['GET', 'POST', 'PUT'])]
    public function restore(int $id, MeetingPointRepository $repo): RedirectResponse|Response
    {
        $meetingpoint = $repo->find($id);
        if (null === $meetingpoint) {
            throw $this->createNotFoundException('exception.meetingpoint.not-found');
        }

        $repo->restore($meetingpoint);

        return $this->redirectToRoute('config_meetingpoint_list');
    }
}
