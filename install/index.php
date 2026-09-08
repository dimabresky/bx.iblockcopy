<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

/**
 * Installer for local module bx.iblockcopy.
 */
class bx_iblockcopy extends CModule
{
    /** @var string */
    public $MODULE_ID = 'bx.iblockcopy';

    /** @var string */
    public $MODULE_VERSION;

    /** @var string */
    public $MODULE_VERSION_DATE;

    /** @var string */
    public $MODULE_NAME;

    /** @var string */
    public $MODULE_DESCRIPTION;

    /** @var string */
    public $PARTNER_NAME;

    /** @var string */
    public $PARTNER_URI;

    /** @var string */
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('BX_IBLOCKCOPY_MODULE_NAME') ?: 'Iblock copy';
        $this->MODULE_DESCRIPTION = Loc::getMessage('BX_IBLOCKCOPY_MODULE_DESC')
            ?: 'Copy iblock metadata and property structure.';
        $this->PARTNER_NAME = Loc::getMessage('BX_IBLOCKCOPY_PARTNER_NAME') ?: 'dimabresky';
        $this->PARTNER_URI = Loc::getMessage('BX_IBLOCKCOPY_PARTNER_URI') ?: 'https://github.com/dimabresky';
    }

    public function DoInstall(): bool
    {
        global $APPLICATION;

        if (!Loader::includeModule('iblock') && !ModuleManager::isModuleInstalled('iblock')) {
            $APPLICATION->ThrowException(
                Loc::getMessage('BX_IBLOCKCOPY_INSTALL_ERROR_IBLOCK')
                    ?: 'Module iblock is required.'
            );

            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);
        $this->installDB();
        $this->installFiles();
        $this->installEvents();

        return true;
    }

    public function DoUninstall(): bool
    {
        $this->unInstallEvents();
        $this->unInstallFiles();
        $this->unInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    public function installDB(): bool
    {
        return true;
    }

    public function unInstallDB(): bool
    {
        return true;
    }

    public function installFiles(): bool
    {
        CopyDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );

        return true;
    }

    public function unInstallFiles(): bool
    {
        DeleteDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );

        return true;
    }

    public function installEvents(): void
    {
        $em = EventManager::getInstance();
        $em->registerEventHandler(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            '\\Bx\\IblockCopy\\Event\\Handlers',
            'onBuildGlobalMenu'
        );
    }

    public function unInstallEvents(): void
    {
        $em = EventManager::getInstance();
        $em->unRegisterEventHandler(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            '\\Bx\\IblockCopy\\Event\\Handlers',
            'onBuildGlobalMenu'
        );
    }

    /**
     * @return array{reference_id: list<string>, reference: list<string>}
     */
    public function GetModuleRightList(): array
    {
        return [
            'reference_id' => ['D', 'R', 'W'],
            'reference' => [
                Loc::getMessage('BX_IBLOCKCOPY_DENIED') ?: 'Denied',
                Loc::getMessage('BX_IBLOCKCOPY_READ') ?: 'Read',
                Loc::getMessage('BX_IBLOCKCOPY_WRITE') ?: 'Write',
            ],
        ];
    }
}
