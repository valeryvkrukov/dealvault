<?php

namespace DealVault\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

class TranslationCompletenessTest extends KernelTestCase
{
    public function testTranslationFilesHaveIdenticalKeys(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        $enPath = $projectDir . '/translations/messages.en.yaml';
        $frPath = $projectDir . '/translations/messages.fr.yaml';

        $enKeys = file_exists($enPath) ? array_keys(Yaml::parse(file_get_contents($enPath)) ?? []) : [];
        $frKeys = file_exists($frPath) ? array_keys(Yaml::parse(file_get_contents($frPath)) ?? []) : [];

        sort($enKeys);
        sort($frKeys);

        $this->assertEquals(
            $enKeys,
            $frKeys,
            'Vocabulary files for English (EN) and French (FR) must contain identical keys.'
        );
    }
}
