<?php

namespace App\Contracts;

interface DescribesProgressStep
{

    public static function getStepMetadata(): array;

    public function getTrackableModel(): TracksProgressInterface;
}
