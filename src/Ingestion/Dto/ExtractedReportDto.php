<?php

namespace DealVault\Ingestion\Dto;

class ExtractedReportDto
{
    public ?float $arr = null;
    public ?int $headcount = null;
    public string $currency = 'USD';

    /** @var string|null A quote from the text that confirms the meaning of ARR */
    public ?string $arrEvidence = null;

    /** @var string|null A quote from the text that confirms the meaning of headcount */
    public ?string $headcountEvidence = null;

    public string $confidenceScore = 'high'; // 'high', 'medium', 'low'
}
