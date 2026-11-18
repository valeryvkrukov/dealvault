<?php

namespace DealVault\Identity\Controller;

use DealVault\Ingestion\Entity\IngestionSource;
use DealVault\Ingestion\Message\ProcessDocumentCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class FounderDashboardController extends AbstractController
{
    #[Route('/founder/dashboard', name: 'founder_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('founder_login');
        }

        return $this->render('portfolio/founder_portal.html.twig');
    }

    #[Route('/founder/upload', name: 'founder_file_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $entityManager, MessageBusInterface $messageBus): Response
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getTenant')) {
            return $this->redirectToRoute('founder_login');
        }

        $file = $request->files->get('report_file');
        if (!$file) {
            $this->addFlash('error', 'No file selected.');
            return $this->redirectToRoute('founder_dashboard');
        }

        $fileName = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['pdf', 'xlsx', 'xls', 'csv'])) {
            $this->addFlash('error', 'Invalid file format. Supported: PDF, XLSX, CSV.');
            return $this->redirectToRoute('founder_dashboard');
        }

        $targetDir = sys_get_temp_dir() . '/dealvault_uploads/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $finalPath = $targetDir . uniqid() . '_' . $fileName;
        $file->move($targetDir, basename($finalPath));

        try {
            $source = new IngestionSource($user->getTenant(), 'file');
            $source->setFileName($fileName);
            $source->setSha256(hash_file('sha256', $finalPath));
            $source->setStatus('queued');

            $entityManager->persist($source);
            $entityManager->flush();

            $messageBus->dispatch(new ProcessDocumentCommand($user->getTenant()->getId(), $fileName, $finalPath));
            $this->addFlash('success', 'Report uploaded successfully! AI extraction pipeline triggered.');
        } catch (ExceptionInterface $e) {
            $this->addFlash('error', 'Pipeline error: ' . $e->getMessage());
        }

        return $this->redirectToRoute('founder_dashboard');
    }

    #[Route('/founder/logout', name: 'founder_logout')]
    public function logout(): void
    {
        // Will be handled by Symfony's security firewall
    }
}
