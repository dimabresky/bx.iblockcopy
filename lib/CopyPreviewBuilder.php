<?php

namespace Bx\IblockCopy;

use Bitrix\Main\Loader;

/**
 * Builds a read-only preview of the source iblock for the admin prepare page.
 */
final class CopyPreviewBuilder
{
    /**
     * @return array<string, mixed>|null
     */
    public function build(int $iblockId): ?array
    {
        if ($iblockId <= 0 || !Loader::includeModule('iblock')) {
            return null;
        }

        $iblock = \CIBlock::GetArrayByID($iblockId);
        if (!is_array($iblock) || (int)($iblock['ID'] ?? 0) <= 0) {
            return null;
        }

        $properties = [];
        $listCount = 0;
        $directoryCount = 0;
        $linkCount = 0;

        $propertyIterator = \CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId]
        );
        while ($property = $propertyIterator->Fetch()) {
            $propertyId = (int)$property['ID'];
            $enumCount = 0;
            if (($property['PROPERTY_TYPE'] ?? '') === 'L') {
                $enumIterator = \CIBlockPropertyEnum::GetList(
                    ['SORT' => 'ASC', 'ID' => 'ASC'],
                    ['PROPERTY_ID' => $propertyId]
                );
                while ($enumIterator->Fetch()) {
                    ++$enumCount;
                }
                ++$listCount;
            }

            $userType = (string)($property['USER_TYPE'] ?? '');
            $userTypeSettings = $property['USER_TYPE_SETTINGS'] ?? [];
            if (!is_array($userTypeSettings) && is_string($userTypeSettings) && $userTypeSettings !== '') {
                $unserialized = unserialize($userTypeSettings, ['allowed_classes' => false]);
                $userTypeSettings = is_array($unserialized) ? $unserialized : [];
            }
            if (!is_array($userTypeSettings)) {
                $userTypeSettings = [];
            }

            $directoryTable = '';
            if ($userType === 'directory') {
                $directoryTable = (string)($userTypeSettings['TABLE_NAME'] ?? '');
                ++$directoryCount;
            }

            $linkIblockId = (int)($property['LINK_IBLOCK_ID'] ?? 0);
            if (in_array($property['PROPERTY_TYPE'] ?? '', ['E', 'G'], true) && $linkIblockId > 0) {
                ++$linkCount;
            }

            $properties[] = [
                'ID' => $propertyId,
                'NAME' => (string)($property['NAME'] ?? ''),
                'CODE' => (string)($property['CODE'] ?? ''),
                'PROPERTY_TYPE' => (string)($property['PROPERTY_TYPE'] ?? ''),
                'USER_TYPE' => $userType,
                'MULTIPLE' => (string)($property['MULTIPLE'] ?? 'N'),
                'ENUM_COUNT' => $enumCount,
                'DIRECTORY_TABLE' => $directoryTable,
                'LINK_IBLOCK_ID' => $linkIblockId,
            ];
        }

        $sites = $iblock['LID'] ?? [];
        if (!is_array($sites)) {
            $sites = $sites !== '' && $sites !== null ? [(string)$sites] : [];
        }

        $sectionUfCount = $this->countSectionUserFields($iblockId);
        $hasElementFormSettings = $this->hasFormSettings('form_element_' . $iblockId);
        $hasSectionFormSettings = $this->hasFormSettings('form_section_' . $iblockId);

        return [
            'ID' => (int)$iblock['ID'],
            'NAME' => (string)($iblock['NAME'] ?? ''),
            'CODE' => (string)($iblock['CODE'] ?? ''),
            'API_CODE' => (string)($iblock['API_CODE'] ?? ''),
            'XML_ID' => (string)($iblock['XML_ID'] ?? ''),
            'IBLOCK_TYPE_ID' => (string)($iblock['IBLOCK_TYPE_ID'] ?? ''),
            'ACTIVE' => (string)($iblock['ACTIVE'] ?? 'Y'),
            'VERSION' => (int)($iblock['VERSION'] ?? 1),
            'LID' => array_values(array_map('strval', $sites)),
            'PROPERTY_COUNT' => count($properties),
            'LIST_PROPERTY_COUNT' => $listCount,
            'DIRECTORY_PROPERTY_COUNT' => $directoryCount,
            'LINK_PROPERTY_COUNT' => $linkCount,
            'SECTION_UF_COUNT' => $sectionUfCount,
            'HAS_ELEMENT_FORM_SETTINGS' => $hasElementFormSettings,
            'HAS_SECTION_FORM_SETTINGS' => $hasSectionFormSettings,
            'PROPERTIES' => $properties,
        ];
    }

    private function countSectionUserFields(int $iblockId): int
    {
        $entityId = 'IBLOCK_' . $iblockId . '_SECTION';
        $count = 0;
        $iterator = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId]);
        while ($iterator->Fetch()) {
            ++$count;
        }

        return $count;
    }

    private function hasFormSettings(string $formId): bool
    {
        if (!class_exists(\CAdminFormSettings::class)) {
            return false;
        }

        $tabs = \CAdminFormSettings::getTabsArray($formId);

        return is_array($tabs) && $tabs !== [];
    }
}
