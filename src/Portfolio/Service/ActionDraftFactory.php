<?php

namespace DealVault\Portfolio\Service;

use DealVault\Portfolio\Entity\PortfolioCompany;

class ActionDraftFactory
{
    /**
     *
     */
    public function createMissingFieldsDraft(
        PortfolioCompany $company,
        string $period,
        array $missingFields,
        string $lang = 'en'
    ): array
    {
        $fieldsList = implode(', ', $missingFields);

        if ($lang === 'fr') {
            $subject = sprintf("Données manquantes pour le portefeuille - %s [%s]", $company->getName(), $period);
            $body = sprintf(
                "Bonjour,\n\nEn vérifiant les rapports pour la période %s, nous avons constaté qu'il manque les indicateurs obligatoires suivants : %s.\n\nMerci de soumettre ces chiffres via votre portail dès que possible.\n\nCordialement,\nL'équipe d'investissement",
                $period,
                $fieldsList
            );
        } else {
            $subject = sprintf("Missing Portfolio Data - %s [%s]", $company->getName(), $period);
            $body = sprintf(
                "Hello,\n\nWhile reviewing the reporting for %s, we noticed that the following required metrics are currently missing: %s.\n\nPlease submit these figures through your portal at your earliest convenience.\n\nBest regards,\nThe Investment Team",
                $period,
                $fieldsList
            );
        }

        return [
            'subject' => $subject,
            'body' => $body,
            'recipient' => sprintf("founder@%s", strtolower(str_replace(' ', '', $company->getName())) . '.com')
        ];
    }
}
