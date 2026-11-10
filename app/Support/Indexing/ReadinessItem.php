<?php

namespace App\Support\Indexing;

/** Một điều kiện trong checklist mở index. */
final readonly class ReadinessItem
{
    public function __construct(
        public string $label,
        public bool $passed,
        public string $detail,
    ) {}
}
