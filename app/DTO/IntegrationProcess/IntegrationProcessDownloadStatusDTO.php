<?php

namespace App\DTO\IntegrationProcess;

use App\DTO\BaseDTO;

class IntegrationProcessDownloadStatusDTO extends BaseDTO
{
    public ?string $recording;
    public ?string $recordingStatus;

    public function __construct(string $recording = null, string $recordingStatus = null)
    {
        parent::__construct();
        $this->recording = $recording;
        $this->recordingStatus = $recordingStatus;
    }
}
