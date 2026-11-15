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
        return $this->render('portfolio/dashboard.html.twig');
    }
}
