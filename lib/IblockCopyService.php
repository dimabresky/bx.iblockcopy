<?php

namespace Bx\IblockCopy;

use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bx\IblockCopy\Copier\AdminFormSettingsCopier;
use Bx\IblockCopy\Copier\IblockMetaCopier;
use Bx\IblockCopy\Copier\PropertyStructureCopier;
use Bx\IblockCopy\Copier\SectionUserFieldCopier;

/**
 * Public API: copy iblock metadata, property structure, section UF and admin forms.
 */
final class IblockCopyService
{
    private const MODULE_ID = 'bx.iblockcopy';

    private IblockMetaCopier $metaCopier;

    private PropertyStructureCopier $propertyCopier;

    private SectionUserFieldCopier $sectionUserFieldCopier;

    private AdminFormSettingsCopier $formSettingsCopier;

    public function __construct(
        ?IblockMetaCopier $metaCopier = null,
        ?PropertyStructureCopier $propertyCopier = null,
        ?SectionUserFieldCopier $sectionUserFieldCopier = null,
        ?AdminFormSettingsCopier $formSettingsCopier = null
    ) {
        $this->metaCopier = $metaCopier ?? new IblockMetaCopier();
        $this->propertyCopier = $propertyCopier ?? new PropertyStructureCopier();
        $this->sectionUserFieldCopier = $sectionUserFieldCopier ?? new SectionUserFieldCopier();
        $this->formSettingsCopier = $formSettingsCopier ?? new AdminFormSettingsCopier();
    }

    public function copy(CopyOptions $options): CopyResult
    {
        $result = new CopyResult();
        $result->setSourceIblockId($options->getSourceIblockId());

        if (!Loader::includeModule('iblock')) {
            $result->addError('Module iblock is not available.');

            return $result;
        }

        if ($options->getSourceIblockId() <= 0) {
            $result->addError('Source iblock ID is required.');

            return $result;
        }

        $source = \CIBlock::GetArrayByID($options->getSourceIblockId());
        if (!is_array($source) || (int)($source['ID'] ?? 0) <= 0) {
            $result->addError('Source iblock was not found.');

            return $result;
        }

        $before = new Event(self::MODULE_ID, 'OnBeforeIblockCopy', [
            'OPTIONS' => $options,
            'SOURCE' => $source,
        ]);
        EventManager::getInstance()->send($before);
        foreach ($before->getResults() as $eventResult) {
            if ($eventResult->getType() === \Bitrix\Main\EventResult::ERROR) {
                $params = $eventResult->getParameters();
                $message = is_array($params) && isset($params['ERROR'])
                    ? (string)$params['ERROR']
                    : 'Copy cancelled by OnBeforeIblockCopy event.';
                $result->addError($message);

                return $result;
            }
        }

        $newId = $this->metaCopier->copy($options, $result);
        if ($newId <= 0) {
            return $result;
        }

        $result->setNewIblockId($newId);
        $this->propertyCopier->copy($options->getSourceIblockId(), $newId, $options, $result);
        $this->sectionUserFieldCopier->copy($options->getSourceIblockId(), $newId, $options, $result);

        if ($result->getErrors() !== []) {
            $this->rollbackCreatedIblock($newId, $result);
            EventManager::getInstance()->send(new Event(self::MODULE_ID, 'OnAfterIblockCopy', [
                'OPTIONS' => $options,
                'RESULT' => $result,
                'SOURCE_IBLOCK_ID' => $options->getSourceIblockId(),
                'NEW_IBLOCK_ID' => 0,
                'ROLLED_BACK' => true,
            ]));

            Logger::error('Iblock copy rolled back after structure errors', [
                'sourceId' => $options->getSourceIblockId(),
                'rolledBackId' => $newId,
                'errors' => $result->getErrors(),
            ]);

            return $result;
        }

        $this->formSettingsCopier->copy(
            $options->getSourceIblockId(),
            $newId,
            $result->getPropertyMap(),
            $options,
            $result
        );

        $result->setSuccess(true);

        EventManager::getInstance()->send(new Event(self::MODULE_ID, 'OnAfterIblockCopy', [
            'OPTIONS' => $options,
            'RESULT' => $result,
            'SOURCE_IBLOCK_ID' => $options->getSourceIblockId(),
            'NEW_IBLOCK_ID' => $newId,
            'ROLLED_BACK' => false,
        ]));

        Logger::info('Iblock copy finished', [
            'sourceId' => $options->getSourceIblockId(),
            'newId' => $newId,
            'success' => true,
        ]);

        return $result;
    }

    private function rollbackCreatedIblock(int $newId, CopyResult $result): void
    {
        if ($newId <= 0) {
            return;
        }

        $deleted = \CIBlock::Delete($newId);
        if ($deleted) {
            $result->setNewIblockId(0);
            $result->addWarning(sprintf(
                'Created iblock #%d was deleted because structure copy failed.',
                $newId
            ));
        } else {
            $result->addWarning(sprintf(
                'Structure copy failed and automatic rollback of iblock #%d did not succeed; remove it manually.',
                $newId
            ));
            Logger::error('Rollback delete failed', ['iblockId' => $newId]);
        }
    }
}
