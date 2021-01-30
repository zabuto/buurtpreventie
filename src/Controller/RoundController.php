<?php

namespace App\Controller;

use App\Entity\Result;
use App\Entity\Round;
use App\Form\RoundMeetingPointType;
use App\Form\RoundResultType;
use App\Form\RoundTimeType;
use App\Form\RoundType;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * RoundController
 *
 * @IsGranted("ROLE_USER")
 */
class RoundController extends AbstractController
{
    /**
     * @Route("/round", name="round_list")
     *
     * @param  EntityManagerInterface $entityManager
     * @return Response
     */
    public function list(EntityManagerInterface $entityManager)
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_COORDINATE') || $this->isGranted('ROLE_ANALYST')) {
            $user = null;
        }

        $repo = $entityManager->getRepository(Round::class);
        $list = $repo->getOrderedResults($user, 'DESC');

        return $this->render('round/list.html.twig', [
            'list' => $list,
        ]);
    }

    /**
     * @Route("/round/{id}", name="round_detail", requirements={"id"="\d+"})
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response
     * @throws NotFoundHttpException
     */
    public function detail($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(Round::class);
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        return $this->render('round/detail.html.twig', [
            'round' => $round,
        ]);
    }

    /**
     * @Route("/round/add", name="round_add")
     * @IsGranted("ROLE_MEMBER")
     *
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     */
    public function add(EntityManagerInterface $entityManager, Request $request)
    {
        $form = $this->createForm(RoundType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $round = $form->getData();
            $entityManager->persist($round);
            $entityManager->flush();

            return $this->redirectToRoute('round_detail', ['id' => $round->getId()]);
        }

        return $this->render('round/form.html.twig', [
            'id'   => null,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/round/{id}/edit", name="round_edit")
     * @IsGranted("ROLE_MEMBER")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @param  Request                $request
     * @return Response|RedirectResponse
     * @throws NotFoundHttpException
     */
    public function edit($id, EntityManagerInterface $entityManager, Request $request)
    {
        $repo = $entityManager->getRepository(Round::class);
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        $form = $this->createForm(RoundType::class, $round);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('round_detail', ['id' => $round->getId()]);
        }

        return $this->render('round/form.html.twig', [
            'id'   => $id,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/round/{id}/delete", name="round_delete")
     * @IsGranted("ROLE_MEMBER")
     *
     * @param  integer                $id
     * @param  EntityManagerInterface $entityManager
     * @return Response|RedirectResponse
     */
    public function delete($id, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(Round::class);
        $round = $repo->find($id);
        if (null === $round) {
            throw $this->createNotFoundException('exception.round.not-found');
        }

        $entityManager->remove($round);
        $entityManager->flush();

        return $this->redirectToRoute('round_list');
    }

    /**
     * @Route("/round/{id}/modal/{type}", name="round_modal_type", requirements={"id"="\d+"})
     *
     * @param  integer                $id
     * @param  string                 $type
     * @param  EntityManagerInterface $entityManager
     * @return Response
     * @throws NotFoundHttpException
     */
    public function modal($id, $type, EntityManagerInterface $entityManager)
    {
        $repo = $entityManager->getRepository(Round::class);
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
            'form'  => $form->createView(),
        ]);
    }
}
