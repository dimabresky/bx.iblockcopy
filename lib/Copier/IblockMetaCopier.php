<?php

namespace Bx\IblockCopy\Copier;

use Bitrix\Iblock\InheritedProperty\IblockTemplates;
use Bitrix\Main\Loader;
use Bx\IblockCopy\CopyOptions;
use Bx\IblockCopy\CopyResult;
use Bx\IblockCopy\Logger;
use Bx\IblockCopy\UniqueCodeGenerator;

/**
 * Copies iblock card metadata: fields, SEO templates, rights, field settings.
 */
final class IblockMetaCopier
{
    private UniqueCodeGenerator $codeGenerator;

    public function __construct(?UniqueCodeGenerator $codeGenerator = null)
    {
        $this->codeGenerator = $codeGenerator ?? new UniqueCodeGenerator();
    }

    public function copy(CopyOptions $options, CopyResult $result): int
    {
        if (!Loader::includeModule('iblock')) {
            $result->addError('Module iblock is not available.');

            return 0;
        }

        $sourceId = $options->getSourceIblockId();
        $source = \CIBlock::GetArrayByID($sourceId);
        if (!is_array($source) || (int)($source['ID'] ?? 0) <= 0) {
            $result->addError('Source iblock was not found.');

            return 0;
        }

        $typeId = $options->getIblockTypeId() !== ''
            ? $options->getIblockTypeId()
            : (string)($source['IBLOCK_TYPE_ID'] ?? '');
        if (!$this->codeGenerator->isIblockTypeValid($typeId)) {
            $result->addError('Invalid iblock type.');

            return 0;
        }

        $siteIds = $options->getSiteIds();
        if ($siteIds === []) {
            $sourceSites = $source['LID'] ?? [];
            if (!is_array($sourceSites)) {
                $sourceSites = $sourceSites !== '' && $sourceSites !== null ? [(string)$sourceSites] : [];
            }
            $siteIds = array_values(array_map('strval', $sourceSites));
        }
        if ($siteIds === []) {
            $result->addError('At least one site must be selected.');

            return 0;
        }

        $name = $options->getName() !== ''
            ? $options->getName()
            : ((string)($source['NAME'] ?? 'Iblock') . ' (copy)');

        $code = $options->getCode() !== ''
            ? $this->codeGenerator->ensureUnique($options->getCode(), 'CODE')
            : $this->codeGenerator->suggestCode((string)($source['CODE'] ?? ''), 'CODE');

        $apiCode = $options->getApiCode() !== ''
            ? $this->codeGenerator->ensureUnique($options->getApiCode(), 'API_CODE')
            : $this->codeGenerator->suggestCode((string)($source['API_CODE'] ?? $code), 'API_CODE');

        $xmlId = $options->getXmlId();
        if ($xmlId === '') {
            $xmlId = $this->codeGenerator->suggestCode((string)($source['XML_ID'] ?? $code), 'XML_ID');
        } else {
            $xmlId = $this->codeGenerator->ensureUnique($xmlId, 'XML_ID');
        }

        $fields = [
            'IBLOCK_TYPE_ID' => $typeId,
            'LID' => $siteIds,
            'NAME' => $name,
            'CODE' => $code,
            'API_CODE' => $apiCode,
            'XML_ID' => $xmlId,
            'ACTIVE' => $options->getActive(),
            'SORT' => (int)($source['SORT'] ?? 500),
            'LIST_MODE' => (string)($source['LIST_MODE'] ?? ''),
            'VERSION' => (int)($source['VERSION'] ?? 1),
            'DESCRIPTION' => (string)($source['DESCRIPTION'] ?? ''),
            'DESCRIPTION_TYPE' => (string)($source['DESCRIPTION_TYPE'] ?? 'text'),
            'SECTION_CHOOSER' => (string)($source['SECTION_CHOOSER'] ?? ''),
            'INDEX_ELEMENT' => (string)($source['INDEX_ELEMENT'] ?? 'Y'),
            'INDEX_SECTION' => (string)($source['INDEX_SECTION'] ?? 'N'),
            'WORKFLOW' => (string)($source['WORKFLOW'] ?? 'N'),
            'BIZPROC' => (string)($source['BIZPROC'] ?? 'N'),
            'SECTION_PROPERTY' => (string)($source['SECTION_PROPERTY'] ?? 'N'),
            'PROPERTY_INDEX' => (string)($source['PROPERTY_INDEX'] ?? 'N'),
            'RIGHTS_MODE' => (string)($source['RIGHTS_MODE'] ?? 'S'),
            'RSS_ACTIVE' => (string)($source['RSS_ACTIVE'] ?? 'N'),
            'RSS_TTL' => (int)($source['RSS_TTL'] ?? 24),
            'RSS_FILE_ACTIVE' => (string)($source['RSS_FILE_ACTIVE'] ?? 'N'),
            'RSS_FILE_LIMIT' => (int)($source['RSS_FILE_LIMIT'] ?? 0),
            'RSS_FILE_DAYS' => (int)($source['RSS_FILE_DAYS'] ?? 0),
            'RSS_YANDEX_ACTIVE' => (string)($source['RSS_YANDEX_ACTIVE'] ?? 'N'),
        ];

        if ($options->isCopyUrlTemplates()) {
            $fields['LIST_PAGE_URL'] = (string)($source['LIST_PAGE_URL'] ?? '');
            $fields['DETAIL_PAGE_URL'] = (string)($source['DETAIL_PAGE_URL'] ?? '');
            $fields['SECTION_PAGE_URL'] = (string)($source['SECTION_PAGE_URL'] ?? '');
            $fields['CANONICAL_PAGE_URL'] = (string)($source['CANONICAL_PAGE_URL'] ?? '');
            $result->addWarning('URL templates were copied as-is; check SEF conflicts on the target site.');
        }

        if ($options->isCopyPicture() && (int)($source['PICTURE'] ?? 0) > 0) {
            $picture = \CFile::MakeFileArray((int)$source['PICTURE']);
            if (is_array($picture)) {
                $fields['PICTURE'] = $picture;
            }
        }

        if ($options->isCopyGroupRights()) {
            $groupId = $this->resolveGroupRights($sourceId, $source);
            if ($groupId !== []) {
                $fields['GROUP_ID'] = $groupId;
            }
        }

        if ($options->isCopySeoTemplates()) {
            $seoTemplates = $this->loadSeoTemplates($sourceId);
            if ($seoTemplates !== []) {
                $fields['IPROPERTY_TEMPLATES'] = $seoTemplates;
            }
        }

        $iblock = new \CIBlock();
        $newId = (int)$iblock->Add($fields);
        if ($newId <= 0) {
            $error = (string)($iblock->LAST_ERROR ?: 'Failed to create iblock.');
            $result->addError($error);
            Logger::error('Iblock create failed', [
                'sourceId' => $sourceId,
                'error' => $error,
            ]);

            return 0;
        }

        if ($options->isCopyFieldSettings()) {
            $this->copyFieldSettings($sourceId, $newId, $result);
        }

        if (
            $options->isCopyGroupRights()
            && (string)($source['RIGHTS_MODE'] ?? 'S') === 'E'
            && class_exists(\CIBlockRights::class)
        ) {
            $this->copyExtendedRights($sourceId, $newId, $result);
        }

        Logger::info('Iblock metadata copied', [
            'sourceId' => $sourceId,
            'newId' => $newId,
        ]);

        return $newId;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<int|string, string>
     */
    private function resolveGroupRights(int $sourceId, array $source): array
    {
        if (isset($source['GROUP_ID']) && is_array($source['GROUP_ID'])) {
            return $source['GROUP_ID'];
        }

        if (method_exists(\CIBlock::class, 'GetGroupPermissions')) {
            $permissions = \CIBlock::GetGroupPermissions($sourceId);
            if (is_array($permissions)) {
                return $permissions;
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function loadSeoTemplates(int $iblockId): array
    {
        try {
            $templatesEntity = new IblockTemplates($iblockId);
            $raw = $templatesEntity->get();
            if (!is_array($raw)) {
                return [];
            }

            $result = [];
            foreach ($raw as $code => $value) {
                if (is_array($value) && isset($value['TEMPLATE'])) {
                    $result[(string)$code] = (string)$value['TEMPLATE'];
                } elseif (is_string($value)) {
                    $result[(string)$code] = $value;
                }
            }

            return $result;
        } catch (\Throwable $e) {
            Logger::error('Failed to load SEO templates', [
                'iblockId' => $iblockId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function copyFieldSettings(int $sourceId, int $newId, CopyResult $result): void
    {
        if (!method_exists(\CIBlock::class, 'GetFields') || !method_exists(\CIBlock::class, 'SetFields')) {
            $result->addWarning('CIBlock::GetFields/SetFields are unavailable; field settings were skipped.');

            return;
        }

        $fields = \CIBlock::GetFields($sourceId);
        if (!is_array($fields) || $fields === []) {
            return;
        }

        \CIBlock::SetFields($newId, $fields);
    }

    private function copyExtendedRights(int $sourceId, int $newId, CopyResult $result): void
    {
        try {
            $rights = new \CIBlockRights($sourceId);
            $list = method_exists($rights, 'GetRights') ? $rights->GetRights() : [];
            if (!is_array($list) || $list === []) {
                return;
            }

            $newRights = new \CIBlockRights($newId);
            if (method_exists($newRights, 'SetRights')) {
                $newRights->SetRights($list);
            }
        } catch (\Throwable $e) {
            $result->addWarning('Extended rights were not copied: ' . $e->getMessage());
            Logger::error('Extended rights copy failed', [
                'sourceId' => $sourceId,
                'newId' => $newId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
