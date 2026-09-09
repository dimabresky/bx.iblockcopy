<?php

namespace Bx\IblockCopy\Copier;

use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bx\IblockCopy\CopyOptions;
use Bx\IblockCopy\CopyResult;
use Bx\IblockCopy\Logger;

/**
 * Copies property definitions, list enums, and directory settings (HL reuse).
 */
final class PropertyStructureCopier
{
    private const MODULE_ID = 'bx.iblockcopy';

    /**
     * Fields that must not be passed into CIBlockProperty::Add.
     *
     * @var list<string>
     */
    private const SKIP_FIELDS = [
        'ID',
        'IBLOCK_ID',
        'TIMESTAMP_X',
        'TMP_ID',
        'VERSION',
    ];

    public function copy(int $sourceIblockId, int $newIblockId, CopyOptions $options, CopyResult $result): void
    {
        if (!$options->isCopyProperties()) {
            return;
        }

        if (!Loader::includeModule('iblock')) {
            $result->addError('Module iblock is not available.');

            return;
        }

        $propertyMap = [];
        $enumMap = [];

        $iterator = \CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => $sourceIblockId]
        );

        while ($property = $iterator->Fetch()) {
            $oldPropertyId = (int)$property['ID'];
            $fields = $this->preparePropertyFields($property, $newIblockId);

            $event = new Event(self::MODULE_ID, 'OnBeforePropertyCopy', [
                'SOURCE_IBLOCK_ID' => $sourceIblockId,
                'NEW_IBLOCK_ID' => $newIblockId,
                'SOURCE_PROPERTY' => $property,
                'FIELDS' => $fields,
            ]);
            EventManager::getInstance()->send($event);
            if ($event->getResults()) {
                foreach ($event->getResults() as $eventResult) {
                    if ($eventResult->getType() === \Bitrix\Main\EventResult::ERROR) {
                        $result->addWarning(
                            'Property copy skipped by event: ' . (string)($property['CODE'] ?? $oldPropertyId)
                        );
                        continue 2;
                    }
                    $params = $eventResult->getParameters();
                    if (is_array($params) && isset($params['FIELDS']) && is_array($params['FIELDS'])) {
                        $fields = $params['FIELDS'];
                    }
                }
            }

            // List DEFAULT_VALUE stores enum ID; remap after enums are created.
            $defaultEnumId = null;
            if (($fields['PROPERTY_TYPE'] ?? '') === 'L' && ($fields['DEFAULT_VALUE'] ?? '') !== '') {
                $defaultEnumId = (int)$fields['DEFAULT_VALUE'];
                unset($fields['DEFAULT_VALUE']);
            }

            $propertyObject = new \CIBlockProperty();
            $newPropertyId = (int)$propertyObject->Add($fields);
            if ($newPropertyId <= 0) {
                $error = (string)($propertyObject->LAST_ERROR ?: 'unknown error');
                $result->addError(
                    sprintf(
                        'Failed to copy property [%s]: %s',
                        (string)($property['CODE'] ?? $oldPropertyId),
                        $error
                    )
                );
                Logger::error('Property copy failed', [
                    'sourcePropertyId' => $oldPropertyId,
                    'error' => $error,
                ]);
                continue;
            }

            $propertyMap[$oldPropertyId] = $newPropertyId;

            if (($property['PROPERTY_TYPE'] ?? '') === 'L') {
                $propertyEnumMap = $this->copyEnums($oldPropertyId, $newPropertyId, $result);
                foreach ($propertyEnumMap as $oldEnumId => $newEnumId) {
                    $enumMap[$oldEnumId] = $newEnumId;
                }

                if ($defaultEnumId !== null && isset($propertyEnumMap[$defaultEnumId])) {
                    $update = new \CIBlockProperty();
                    $update->Update($newPropertyId, [
                        'DEFAULT_VALUE' => $propertyEnumMap[$defaultEnumId],
                    ]);
                }
            }

            if (($property['USER_TYPE'] ?? '') === 'directory') {
                $tableName = $this->extractDirectoryTable($property);
                if ($tableName !== '') {
                    $result->addWarning(
                        sprintf(
                            'Directory property [%s] reuses Highload table "%s".',
                            (string)($property['CODE'] ?? $oldPropertyId),
                            $tableName
                        )
                    );
                }
            }

            EventManager::getInstance()->send(new Event(self::MODULE_ID, 'OnAfterPropertyCopy', [
                'SOURCE_IBLOCK_ID' => $sourceIblockId,
                'NEW_IBLOCK_ID' => $newIblockId,
                'SOURCE_PROPERTY_ID' => $oldPropertyId,
                'NEW_PROPERTY_ID' => $newPropertyId,
            ]));
        }

        $result->setPropertyMap($propertyMap);
        $result->setEnumMap($enumMap);

        Logger::info('Properties copied', [
            'sourceIblockId' => $sourceIblockId,
            'newIblockId' => $newIblockId,
            'propertyCount' => count($propertyMap),
        ]);
    }

    /**
     * @param array<string, mixed> $property
     * @return array<string, mixed>
     */
    private function preparePropertyFields(array $property, int $newIblockId): array
    {
        $fields = $property;
        foreach (self::SKIP_FIELDS as $skip) {
            unset($fields[$skip]);
        }

        $fields['IBLOCK_ID'] = $newIblockId;

        if (!isset($fields['CODE']) || trim((string)$fields['CODE']) === '') {
            $fields['CODE'] = 'PROP_' . (int)($property['ID'] ?? 0);
        }

        $userTypeSettings = $fields['USER_TYPE_SETTINGS'] ?? null;
        if (is_string($userTypeSettings) && $userTypeSettings !== '') {
            $unserialized = unserialize($userTypeSettings, ['allowed_classes' => false]);
            $fields['USER_TYPE_SETTINGS'] = is_array($unserialized) ? $unserialized : [];
        } elseif (!is_array($userTypeSettings)) {
            $fields['USER_TYPE_SETTINGS'] = [];
        }

        // Keep LINK_IBLOCK_ID as in source (plan: leave original).
        // Directory USER_TYPE_SETTINGS.TABLE_NAME is reused as-is.

        return $fields;
    }

    /**
     * @return array<int, int>
     */
    private function copyEnums(int $oldPropertyId, int $newPropertyId, CopyResult $result): array
    {
        $map = [];
        $iterator = \CIBlockPropertyEnum::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['PROPERTY_ID' => $oldPropertyId]
        );

        $enumObject = new \CIBlockPropertyEnum();
        while ($enum = $iterator->Fetch()) {
            $oldEnumId = (int)$enum['ID'];
            $newEnumId = (int)$enumObject->Add([
                'PROPERTY_ID' => $newPropertyId,
                'VALUE' => (string)($enum['VALUE'] ?? ''),
                'DEF' => (string)($enum['DEF'] ?? 'N'),
                'SORT' => (int)($enum['SORT'] ?? 500),
                'XML_ID' => (string)($enum['XML_ID'] ?? ''),
            ]);

            if ($newEnumId <= 0) {
                $result->addError(sprintf(
                    'Failed to copy list value "%s" for property #%d.',
                    (string)($enum['VALUE'] ?? $oldEnumId),
                    $oldPropertyId
                ));
                continue;
            }

            $map[$oldEnumId] = $newEnumId;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $property
     */
    private function extractDirectoryTable(array $property): string
    {
        $settings = $property['USER_TYPE_SETTINGS'] ?? [];
        if (is_string($settings) && $settings !== '') {
            $unserialized = unserialize($settings, ['allowed_classes' => false]);
            $settings = is_array($unserialized) ? $unserialized : [];
        }
        if (!is_array($settings)) {
            return '';
        }

        return (string)($settings['TABLE_NAME'] ?? '');
    }
}
