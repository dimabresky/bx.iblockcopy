<?php

namespace Bx\IblockCopy\Copier;

use Bx\IblockCopy\CopyOptions;
use Bx\IblockCopy\CopyResult;
use Bx\IblockCopy\Logger;

/**
 * Copies common admin form tab settings (element + section edit forms).
 *
 * Remaps PROPERTY_{oldId} field keys using the property map from structure copy.
 * Reads only common options (USER_ID = 0), never personal user overrides.
 */
final class AdminFormSettingsCopier
{
    public function copy(
        int $sourceIblockId,
        int $newIblockId,
        array $propertyMap,
        CopyOptions $options,
        CopyResult $result
    ): void {
        if (!$options->isCopyElementFormSettings()) {
            return;
        }

        if (!class_exists(\CAdminFormSettings::class)) {
            $result->addWarning('CAdminFormSettings is unavailable; form tabs were not copied.');

            return;
        }

        $this->copyForm(
            'form_element_' . $sourceIblockId,
            'form_element_' . $newIblockId,
            $propertyMap,
            $result,
            true,
            false
        );

        $this->copyForm(
            'form_section_' . $sourceIblockId,
            'form_section_' . $newIblockId,
            $propertyMap,
            $result,
            false,
            !$options->isCopySectionUserFields()
        );
    }

    /**
     * @param array<int, int> $propertyMap
     */
    private function copyForm(
        string $sourceFormId,
        string $targetFormId,
        array $propertyMap,
        CopyResult $result,
        bool $remapProperties,
        bool $dropUserFields
    ): void {
        try {
            $tabs = $this->getCommonTabsArray($sourceFormId);
            if ($tabs === []) {
                return;
            }

            if ($remapProperties) {
                $tabs = $this->remapPropertyFields($tabs, $propertyMap, $sourceFormId, $result);
            }

            if ($dropUserFields) {
                $tabs = $this->dropUserFields($tabs, $sourceFormId, $result);
            }

            \CAdminFormSettings::setTabsArray($targetFormId, $tabs, true, false);

            Logger::info('Admin form settings copied', [
                'sourceFormId' => $sourceFormId,
                'targetFormId' => $targetFormId,
            ]);
        } catch (\Throwable $e) {
            $result->addWarning(sprintf(
                'Failed to copy form settings "%s": %s',
                $sourceFormId,
                $e->getMessage()
            ));
            Logger::error('Admin form settings copy failed', [
                'sourceFormId' => $sourceFormId,
                'targetFormId' => $targetFormId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Loads tabs from common user options only (USER_ID = 0).
     *
     * Parsing mirrors \CAdminFormSettings::getTabsArray, but without personal overrides.
     *
     * @return array<string, array{TAB: string, FIELDS: array<string, string>}>
     */
    private function getCommonTabsArray(string $formId): array
    {
        $customTabs = \CUserOptions::GetOption('form', $formId, false, 0);
        if (!is_array($customTabs) || empty($customTabs['tabs'])) {
            return [];
        }

        $arCustomTabs = [];
        $arTabs = explode('--;--', (string)$customTabs['tabs']);
        foreach ($arTabs as $customFields) {
            if ($customFields === '') {
                continue;
            }

            $arCustomFields = explode('--,--', $customFields);
            $arCustomTabID = '';
            foreach ($arCustomFields as $customField) {
                if ($arCustomTabID === '') {
                    [$arCustomTabID, $arCustomTabName] = array_pad(
                        explode('--#--', $customField, 2),
                        2,
                        ''
                    );
                    $arCustomTabs[$arCustomTabID] = [
                        'TAB' => $arCustomTabName,
                        'FIELDS' => [],
                    ];
                } else {
                    [$arCustomFieldID, $arCustomFieldName] = array_pad(
                        explode('--#--', $customField, 2),
                        2,
                        ''
                    );
                    $arCustomFieldName = ltrim($arCustomFieldName, "* -\xa0\xc2");
                    $arCustomTabs[$arCustomTabID]['FIELDS'][$arCustomFieldID] = $arCustomFieldName;
                }
            }
        }

        return $arCustomTabs;
    }

    /**
     * @param array<string, mixed> $tabs
     * @param array<int, int> $propertyMap
     * @return array<string, mixed>
     */
    private function remapPropertyFields(
        array $tabs,
        array $propertyMap,
        string $sourceFormId,
        CopyResult $result
    ): array {
        $dropped = [];
        $remapped = [];
        foreach ($tabs as $tabId => $tab) {
            if (!is_array($tab)) {
                $remapped[$tabId] = $tab;
                continue;
            }

            $fields = $tab['FIELDS'] ?? [];
            if (!is_array($fields)) {
                $remapped[$tabId] = $tab;
                continue;
            }

            $newFields = [];
            foreach ($fields as $fieldId => $fieldName) {
                $resolved = $this->resolvePropertyFieldId((string)$fieldId, $propertyMap);
                if ($resolved === null) {
                    $dropped[] = (string)$fieldId;
                    continue;
                }
                $newFields[$resolved] = $fieldName;
            }

            $tab['FIELDS'] = $newFields;
            $remapped[$tabId] = $tab;
        }

        if ($dropped !== []) {
            $result->addWarning(sprintf(
                'Form "%s": dropped unmapped property fields: %s',
                $sourceFormId,
                implode(', ', array_values(array_unique($dropped)))
            ));
        }

        return $remapped;
    }

    /**
     * @param array<string, mixed> $tabs
     * @return array<string, mixed>
     */
    private function dropUserFields(array $tabs, string $sourceFormId, CopyResult $result): array
    {
        $dropped = [];
        $cleaned = [];
        foreach ($tabs as $tabId => $tab) {
            if (!is_array($tab) || !isset($tab['FIELDS']) || !is_array($tab['FIELDS'])) {
                $cleaned[$tabId] = $tab;
                continue;
            }

            $newFields = [];
            foreach ($tab['FIELDS'] as $fieldId => $fieldName) {
                if (str_starts_with((string)$fieldId, 'UF_')) {
                    $dropped[] = (string)$fieldId;
                    continue;
                }
                $newFields[$fieldId] = $fieldName;
            }

            $tab['FIELDS'] = $newFields;
            $cleaned[$tabId] = $tab;
        }

        if ($dropped !== []) {
            $result->addWarning(sprintf(
                'Form "%s": dropped UF fields because section user fields were not copied: %s',
                $sourceFormId,
                implode(', ', array_values(array_unique($dropped)))
            ));
        }

        return $cleaned;
    }

    /**
     * @param array<int, int> $propertyMap
     * @return string|null Null when PROPERTY_* cannot be remapped and must be dropped
     */
    private function resolvePropertyFieldId(string $fieldId, array $propertyMap): ?string
    {
        if (preg_match('/^PROPERTY_(\d+)$/', $fieldId, $matches) !== 1) {
            return $fieldId;
        }

        $oldId = (int)$matches[1];
        if (!isset($propertyMap[$oldId])) {
            return null;
        }

        return 'PROPERTY_' . $propertyMap[$oldId];
    }
}
