<?php

namespace App\Console\Commands;

use AzureOss\Storage\Blob\BlobServiceClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:ensure-container')]
#[Description('Create the Blob Storage container for attachments if it does not exist (local Azurite)')]
class EnsureMediaContainer extends Command
{
    public function handle(): int
    {
        $connectionString = config('filesystems.disks.azure.connection_string');
        $container = config('filesystems.disks.azure.container');

        if (! is_string($connectionString) || $connectionString === '') {
            $this->components->info('No AZURE_STORAGE_CONNECTION_STRING set; on Azure the container is created by Terraform.');

            return self::SUCCESS;
        }

        BlobServiceClient::fromConnectionString($connectionString)
            ->getContainerClient($container)
            ->createIfNotExists();

        $this->components->info("Container [{$container}] is ready.");

        return self::SUCCESS;
    }
}
