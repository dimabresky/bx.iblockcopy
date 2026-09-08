<?php

/**
 * Autoload map for bx.iblockcopy.
 */

use Bitrix\Main\Loader;
use Bx\IblockCopy\CopyOptions;
use Bx\IblockCopy\CopyPreviewBuilder;
use Bx\IblockCopy\CopyResult;
use Bx\IblockCopy\Copier\IblockMetaCopier;
use Bx\IblockCopy\Copier\PropertyStructureCopier;
use Bx\IblockCopy\Event\Handlers;
use Bx\IblockCopy\IblockCopyService;
use Bx\IblockCopy\Logger;
use Bx\IblockCopy\UniqueCodeGenerator;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loader::registerAutoLoadClasses(
    'bx.iblockcopy',
    [
        CopyOptions::class => 'lib/CopyOptions.php',
        CopyResult::class => 'lib/CopyResult.php',
        CopyPreviewBuilder::class => 'lib/CopyPreviewBuilder.php',
        IblockCopyService::class => 'lib/IblockCopyService.php',
        UniqueCodeGenerator::class => 'lib/UniqueCodeGenerator.php',
        Logger::class => 'lib/Logger.php',
        IblockMetaCopier::class => 'lib/Copier/IblockMetaCopier.php',
        PropertyStructureCopier::class => 'lib/Copier/PropertyStructureCopier.php',
        Handlers::class => 'lib/Event/Handlers.php',
    ]
);
