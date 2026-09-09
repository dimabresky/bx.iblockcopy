<?php

namespace Bx\IblockCopy\Copier;

use Bx\IblockCopy\CopyOptions;
use Bx\IblockCopy\CopyResult;
use Bx\IblockCopy\Logger;

/**
 * Copies section user-field definitions (IBLOCK_{ID}_SECTION), including enumerations.
 */
final class SectionUserFieldCopier
{
    /**
     * Fields that must not be passed into CUserTypeEntity::Add as-is.
     *
     * @var list<string>
     */
    private const SKIP_FIELDS = [
        'ID',
        'ENTITY_ID',
        'FIELD_ID',
    ];

    public function copy(
        int $sourceIblockId,
        int $newIblockId,
        CopyOptions $options,
        CopyResult $result
    ): void {
        if (!$options->isCopySectionUserFields()) {
            return;
        }

        $sourceEntityId = 'IBLOCK_' . $sourceIblockId . '_SECTION';
        $targetEntityId = 'IBLOCK_' . $newIblockId . '_SECTION';

        $existingNames = $this->loadExistingFieldNames($targetEntityId);

        $iterator = \CUserTypeEntity::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['ENTITY_ID' => $sourceEntityId]
        );

        $copied = 0;
        while ($row = $iterator->Fetch()) {
            $sourceFieldId = (int)($row['ID'] ?? 0);
            if ($sourceFieldId <= 0) {
                continue;
            }

            $field = \CUserTypeEntity::GetByID($sourceFieldId);
            if (!is_array($field)) {
                $result->addError(sprintf(
                    'Failed to load section user field #%d.',
                    $sourceFieldId
                ));
                continue;
            }

            $fieldName = (string)($field['FIELD_NAME'] ?? '');
            if ($fieldName === '') {
                $result->addError(sprintf(
                    'Section user field #%d has empty FIELD_NAME.',
                    $sourceFieldId
                ));
                continue;
            }

            if (isset($existingNames[$fieldName])) {
                $result->addWarning(sprintf(
                    'Section user field "%s" already exists on target; skipped.',
                    $fieldName
                ));
                continue;
            }

            $fields = $this->prepareFields($field, $targetEntityId, $sourceIblockId, $newIblockId);
            $userType = new \CUserTypeEntity();
            $newFieldId = (int)$userType->Add($fields);
            if ($newFieldId <= 0) {
                $error = (string)($userType->LAST_ERROR ?: 'unknown error');
                $result->addError(sprintf(
                    'Failed to copy section user field [%s]: %s',
                    $fieldName,
                    $error
                ));
                Logger::error('Section UF copy failed', [
                    'fieldName' => $fieldName,
                    'error' => $error,
                ]);
                continue;
            }

            $existingNames[$fieldName] = $newFieldId;

            if (($field['USER_TYPE_ID'] ?? '') === 'enumeration') {
                $this->copyEnumeration($sourceFieldId, $newFieldId, $fieldName, $result);
            }

            ++$copied;
        }

        Logger::info('Section user fields copied', [
            'sourceIblockId' => $sourceIblockId,
            'newIblockId' => $newIblockId,
            'copied' => $copied,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function loadExistingFieldNames(string $entityId): array
    {
        $names = [];
        $iterator = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId]);
        while ($row = $iterator->Fetch()) {
            $name = (string)($row['FIELD_NAME'] ?? '');
            if ($name !== '') {
                $names[$name] = (int)$row['ID'];
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function prepareFields(
        array $field,
        string $targetEntityId,
        int $sourceIblockId,
        int $newIblockId
    ): array {
        $fields = $field;
        foreach (self::SKIP_FIELDS as $skip) {
            unset($fields[$skip]);
        }

        $fields['ENTITY_ID'] = $targetEntityId;

        if (!isset($fields['SETTINGS']) || !is_array($fields['SETTINGS'])) {
            $fields['SETTINGS'] = [];
        }

        if (
            isset($fields['SETTINGS']['IBLOCK_ID'])
            && (int)$fields['SETTINGS']['IBLOCK_ID'] === $sourceIblockId
        ) {
            $fields['SETTINGS']['IBLOCK_ID'] = $newIblockId;
        }

        foreach (['EDIT_FORM_LABEL', 'LIST_COLUMN_LABEL', 'LIST_FILTER_LABEL', 'ERROR_MESSAGE', 'HELP_MESSAGE'] as $labelKey) {
            if (!isset($fields[$labelKey]) || !is_array($fields[$labelKey])) {
                $fields[$labelKey] = is_string($fields[$labelKey] ?? null)
                    ? ['ru' => (string)$fields[$labelKey]]
                    : [];
            }
        }

        return $fields;
    }

    private function copyEnumeration(
        int $sourceFieldId,
        int $newFieldId,
        string $fieldName,
        CopyResult $result
    ): void {
        $values = [];
        $index = 0;
        $iterator = \CUserFieldEnum::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['USER_FIELD_ID' => $sourceFieldId]
        );
        while ($enum = $iterator->Fetch()) {
            $values['n' . $index] = [
                'XML_ID' => (string)($enum['XML_ID'] ?? ''),
                'VALUE' => (string)($enum['VALUE'] ?? ''),
                'DEF' => (string)($enum['DEF'] ?? 'N'),
                'SORT' => (int)($enum['SORT'] ?? 500),
            ];
            ++$index;
        }

        if ($values === []) {
            return;
        }

        $enumObject = new \CUserFieldEnum();
        if (!$enumObject->SetEnumValues($newFieldId, $values)) {
            $result->addError(sprintf(
                'Failed to copy enumeration values for section user field [%s].',
                $fieldName
            ));
        }
    }
}
