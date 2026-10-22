<?php

declare(strict_types=1);

namespace App\Contracts;

interface MergesPdfDocuments
{
    /** @param list<string> $pdfBinaries */
    public function merge(array $pdfBinaries): string;
}
