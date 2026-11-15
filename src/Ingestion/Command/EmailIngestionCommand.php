<?php

namespace DealVault\Ingestion\Command;

use DealVault\Portfolio\Entity\CompanyContact;
use DealVault\Ingestion\Service\MailpitClient;
use DealVault\Ingestion\Message\ProcessDocumentCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:ingest-emails',
    description: 'Polls Mailpit API, matches sender with CompanyContact and dispatches attachments to the Messenger queue'
)]
class EmailIngestionCommand extends Command
{
    private MailpitClient $mailpitClient;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;

    public function __construct(
        MailpitClient $mailpitClient,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus
    ) {
        parent::__construct();
        $this->mailpitClient = $mailpitClient;
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $messages = $this->mailpitClient->fetchMessages();

        if (empty($messages)) {
            $output->writeln('<info>No new emails found in Mailpit.</info>');
            return Command::SUCCESS;
        }

        $contactRepository = $this->entityManager->getRepository(CompanyContact::class);

        foreach ($messages as $msg) {
            $messageId = $msg['ID'];
            $senderEmail = $msg['From']['Address'] ?? null;
            $attachments = $msg['Attachments'] ?? [];

            if (!$senderEmail || empty($attachments)) {
                continue;
            }

            // Find founder contact in DB to determine the context of the organization (Tenant)
            /** @var CompanyContact|null $contact */
            $contact = $contactRepository->findOneBy(['email' => $senderEmail]);

            if ($contact === null) {
                $output->writeln(sprintf('<comment>Skipping email from unknown sender: %s</comment>', $senderEmail));
                continue;
            }

            $company = $contact->getCompany();
            $tenantId = $company->getTenantId();

            foreach ($attachments as $attachment) {
                $partId = $attachment['PartID'];
                $fileName = $attachment['Name'];

                // Create isolated temporary file name
                $tmpPath = sys_get_temp_dir() . '/mail_attach_' . uniqid() . '_' . $fileName;

                $output->writeln(sprintf('Processing attachment <comment>%s</comment> from <info>%s</info>', $fileName, $senderEmail));

                if ($this->mailpitClient->downloadAttachment($messageId, $partId, $tmpPath)) {
                    // Push command to Symfony Messenger async bus (from Milestone 2)
                    $this->messageBus->dispatch(new ProcessDocumentCommand($tenantId, $fileName, $tmpPath));
                }
            }

            $this->mailpitClient->deleteMessage($messageId);
        }

        return Command::SUCCESS;
    }
}
