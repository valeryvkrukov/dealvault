<?php

namespace DealVault\Portfolio\Service;

use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\CompanyContact;
use Doctrine\ORM\EntityManagerInterface;

class ActionDraftFactory
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function createMissingFieldsDraft(
        PortfolioCompany $company,
        string $period,
        array $missingFields,
        string $lang = 'en'
    ): array
    {
        $fieldsList = implode(', ', $missingFields);

        $contactRepository = $this->entityManager->getRepository(CompanyContact::class);
        $contact = $contactRepository->findOneBy(['company' => $company->getId(), 'isPrimary' => true])
            ?? $contactRepository->findOneBy(['company' => $company->getId()]);

        $recipientEmail = $contact !== null ? $contact->getEmail() : 'founder@placeholder.com';
        $recipientName = $contact !== null ? $contact->getFullName() : 'Founder';

        if ($lang === 'fr') {
            $subject = sprintf("Données manquantes pour le portefeuille - %s [%s]", $company->getName(), $period);
            $body = sprintf(
                "Bonjour %s,\n\nEn vérifiant les rapports pour la période %s, nous avons constaté qu'il manque les indicateurs obligatoires suivants : %s.\n\nMerci de soumettre ces chiffres via votre portail dès que possible.\n\nCordialement,\nL'équipe d'investissement",
                $recipientName,
                $period,
                $fieldsList
            );
        } else {
            $subject = sprintf("Missing Portfolio Data - %s [%s]", $company->getName(), $period);
            $body = sprintf(
                "Hello %s,\n\nWhile reviewing the reporting for %s, we noticed that the following required metrics are currently missing: %s.\n\nPlease submit these figures through your portal at your earliest convenience.\n\nBest regards,\nThe Investment Team",
                $recipientName,
                $period,
                $fieldsList
            );
        }

        return [
            'subject' => $subject,
            'body' => $body,
            'recipient' => $recipientEmail
        ];
    }
}
