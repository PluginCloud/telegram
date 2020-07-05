<?php


namespace PluginCloud\Telegram;


use AurimasNiekis\TdLibSchema\TdlibParameters;

class Parameter extends TdlibParameters
{
    public bool $useTestDc = false;

    public string $databaseDirectory = "td_db_dir";

    public string $filesDirectory = "td_file_dir";

    public bool $useFileDatabase = true;

    public bool $useChatInfoDatabase = true;

    public bool $useMessageDatabase = false;

    public bool $useSecretChats = false;

    public int $apiId = 0;

    public string $apiHash = "";

    public string $systemLanguageCode = "en";

    public string $deviceModel = "client";

    public string $systemVersion = "1.0";

    public string $applicationVersion = "0.0.1";

    public bool $enableStorageOptimizer = true;

    public bool $ignoreFileNames = false;

    public function __construct()
    {
        parent::__construct(
            $this->useTestDc, $this->databaseDirectory, $this->filesDirectory,
            $this->useFileDatabase, $this->useChatInfoDatabase, $this->useMessageDatabase, $this->useSecretChats,
            $this->apiId, $this->apiHash, $this->systemLanguageCode, $this->deviceModel, $this->systemVersion,
            $this->applicationVersion, $this->enableStorageOptimizer, $this->ignoreFileNames
        );
    }
}
