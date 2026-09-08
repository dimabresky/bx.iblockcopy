<?php

namespace Bx\IblockCopy\Event;

use Bitrix\Main\Localization\Loc;

/**
 * Module event handlers (admin menu and future hooks).
 */
final class Handlers
{
    /**
     * @param array<string, mixed> $aGlobalMenu
     * @param array<string, mixed> $aModuleMenu
     */
    public static function onBuildGlobalMenu(array &$aGlobalMenu, array &$aModuleMenu): void
    {
        global $APPLICATION;

        if ($APPLICATION->GetGroupRight('bx.iblockcopy') < 'R') {
            return;
        }

        Loc::loadMessages(__FILE__);

        $aModuleMenu[] = [
            'parent_menu' => 'global_menu_content',
            'section' => 'bx_iblockcopy',
            'sort' => 500,
            'text' => Loc::getMessage('BX_IBLOCKCOPY_MENU_TEXT') ?: 'Iblock copy',
            'title' => Loc::getMessage('BX_IBLOCKCOPY_MENU_TITLE') ?: 'Copy iblock structure',
            'url' => 'bx_iblockcopy_copy.php?lang=' . LANGUAGE_ID,
            'icon' => 'iblock_menu_icon_types',
            'page_icon' => 'iblock_page_icon',
            'items_id' => 'menu_bx_iblockcopy',
            'items' => [],
        ];
    }
}
