<?php

namespace DealVault\Identity\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FounderDashboardController extends AbstractController
{
    #[Route('/founder/dashboard', name: 'founder_dashboard')]
    public function index(): Response
    {
        return new Response('<html><body>Welcome to Founder Portal</body></html>');
    }

    #[Route('/founder/login', name: 'founder_login', methods: ['GET'])]
    public function loginPrompt(): Response
    {
        return new Response('<html><body>Founder Login Form</body></html>');
    }
}
