<?php

namespace DealVault\Portfolio\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'staff_dashboard')]
    public function index(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('staff_login');
        }

        return $this->render('portfolio/dashboard.html.twig');
    }

    #[Route('/dashboard/review', name: 'staff_review_queue')]
    public function reviewQueue(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('staff_login');
        }

        return $this->render('portfolio/review_queue.html.twig');
    }

    #[Route('/dashboard/dealflow', name: 'staff_dealflow_kanban')]
    public function dealflowKanban(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('staff_login');
        }

        return $this->render('portfolio/dealflow_kanban.html.twig');
    }
}
