<?php

namespace App\Controller;

use App\Entity\MeetingPoint;
use App\Form\MeetingPointType;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ConfigMeetingPointController
 *
 * @IsGranted("ROLE_ADMIN")
 */
class ConfigMeetingPointController extends AbstractController
{
    /**
     * @Route("/admin/meeting-point", name="config_meetingpoint_list")
     *
     * @param  EntityManagerInterface $entityManager
     * @return Response
     */
    public function list(EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(MeetingPoint::class);
        $list = $repo->findBy([], ['description' => 'ASC']);

        return $this->render('config/meetingpoint_list.html.twig', [
            'list' => $list,
        ]);
    }

    /**
     * @Route("/admin/meeting-point/add", name="config_meetingpoint_add")
     *
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     */
    public function add(EntityManagerInterface $entityManager, Request $request)
    {
        $form = $this->createForm(MeetingPointType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $meetingpoint = $form->getData();
            $entityManager->persist($meetingpoint);
            $entityManager->flush();

            return $this->redirectToRoute('config_meetingpoint_list');
        }

        return $this->render('config/meetingpoint_form.html.twig', [
            'id'    => null,
            'point' => null,
            'form'  => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/meeting-point/{id}/edit", name="config_meetingpoint_edit")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function edit($id, EntityManagerInterface $entityManager, Request $request)
    {
        $repo = $entityManager->getRepository(MeetingPoint::class);
        $meetingpoint = $repo->find($id);
        if (null === $meetingpoint) {
            $this->createNotFoundException('exception.meetingpoint.not-found');
        }

        $form = $this->createForm(MeetingPointType::class, $meetingpoint);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('config_meetingpoint_list');
        }

        return $this->render('config/meetingpoint_form.html.twig', [
            'id'      => $id,
            'point'   => $meetingpoint->getLocation(),
            'deleted' => $meetingpoint->isDeleted(),
            'form'    => $form->createView(),
        ]);
    }

    /**
     * @Route("/admin/meeting-point/{id}/delete", name="config_meetingpoint_delete")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response|RedirectResponse
     */
    public function delete($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(MeetingPoint::class);
        $meetingpoint = $repo->find($id);
        if (null === $meetingpoint) {
            $this->createNotFoundException('exception.meetingpoint.not-found');
        }

        $entityManager->remove($meetingpoint);
        $entityManager->flush();

        return $this->redirectToRoute('config_meetingpoint_list');
    }

    /**
     * @Route("/admin/meeting-point/{id}/restore", name="config_meetingpoint_restore")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function restore($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(MeetingPoint::class);
        $meetingpoint = $repo->find($id);
        if (null === $meetingpoint) {
            $this->createNotFoundException('exception.meetingpoint.not-found');
        }

        $meetingpoint->restore();
        $entityManager->flush();

        return $this->redirectToRoute('config_meetingpoint_list');
    }
}
