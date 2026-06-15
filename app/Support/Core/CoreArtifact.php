<?php

namespace App\Support\Core;

class CoreArtifact
{
    public function __construct(
        public readonly string $content,
        public readonly string $contentType,
        public readonly string $filename,
        public readonly string $sourceUrl,
    ) {}
}
