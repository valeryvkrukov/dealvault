<?php

namespace DealVault\Tests\Ingestion\Command;

use DealVault\Identity\Entity\Tenant;
use DealVault\Portfolio\Entity\PortfolioCompany;
use DealVault\Portfolio\Entity\CompanyContact;
use DealVault\Ingestion\Message\ProcessDocumentCommand;
use DealVault\Ingestion\Service\MailpitClient;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Doctrine\ORM\EntityManagerInterface;

class EmailIngestionTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->getConnection()->beginTransaction();
    }

    /**
     * @throws Exception
     */
    protected function tearDown(): void
    {
        if ($this->entityManager !== null) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->getConnection()->rollBack();
            }
            $this->entityManager->close();
            $this->entityManager = null;
        }
        parent::tearDown();
    }

    public function testEmailIngestionPipelineProcessesAttachmentsSuccessfully(): void
    {
        // 1. Init fund, company and contact of founder in DB
        $tenant = new Tenant('Email Ingestion Fund');
        $this->entityManager->persist($tenant);

        $company = new PortfolioCompany($tenant, 'SpaceX Innovations');
        $this->entityManager->persist($company);

        // Founder with confirmed email, who will be simulated in email
        $contact = new CompanyContact($company, 'Elon Musk', 'elon@spacex.com', 'founder');
        $this->entityManager->persist($contact);

        $this->entityManager->flush();

        // Activate tenant filter for test context
        $filters = $this->entityManager->getFilters();
        if ($filters->has('tenant_filter')) {
            $filter = $filters->enable('tenant_filter');
            $filter->setParameter('tenant_id', $tenant->getId());
        }

        // 2. Form mock responses for Mailpit API
        $messagesListBody = [
            'messages' => [
                [
                    'ID' => 'msg-secure-999',
                    'From' => ['Address' => 'elon@spacex.com'],
                    'Subject' => 'Q3 Financial Deliverables',
                    'Attachments' => [
                        [
                            'PartID' => 'part-4',
                            'Name' => 'financial_report_q3.pdf',
                            'Size' => 1024
                        ]
                    ]
                ]
            ]
        ];

        // Prepare queue of responses: 1) List of messages, 2) Download attachment, 3) Delete message
        $mockResponses = [
            new MockResponse(json_encode($messagesListBody), [
                'http_code' => 200,
                'headers' => [
                    'content-type' => 'application/json'
                ]
            ]),
            new MockResponse('Fake PDF Binary Data Content', ['http_code' => 200]),
            new MockResponse('', ['http_code' => 200])
        ];

        $mockHttpClient = new MockHttpClient($mockResponses);

        // Substitute the real MailpitClient in the container with its test version using MockHttpClient
        $mockMailpitClient = new MailpitClient($mockHttpClient);
        self::getContainer()->set(MailpitClient::class, $mockMailpitClient);

        // 3. Create isolated in-memory MessageBus substitution strictly for this test (Remove dependency on DSN)
        $inMemoryTransport = new InMemoryTransport();
        $mockMessageBus = $this->createMock(MessageBusInterface::class);

        // Intercept the call to send a command to the worker
        $mockMessageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($tenant) {
                return $command instanceof ProcessDocumentCommand
                    && $command->getTenantId() === $tenant->getId()
                    && $command->getFileName() === 'financial_report_q3.pdf';
            }))
            ->willReturnCallback(function ($command) use ($inMemoryTransport) {
                return $inMemoryTransport->send(new Envelope($command));
            });

        // Inject the mocked message bus into the application container before running the console command
        self::getContainer()->set(MessageBusInterface::class, $mockMessageBus);

        // 4. Run the console command through CommandTester
        $application = new Application(self::$kernel);
        $command = $application->find('app:ingest-emails');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Processing attachment financial_report_q3.pdf', $output);

        // 5. Check that the command successfully fell into our test transport
        $dispatchedMessages = $inMemoryTransport->get();
        $this->assertCount(1, $dispatchedMessages);

        $envelopeCommand = $dispatchedMessages[0]->getMessage();
        $this->assertInstanceOf(ProcessDocumentCommand::class, $envelopeCommand);
        $this->assertEquals('financial_report_q3.pdf', $envelopeCommand->getFileName());
    }
}
