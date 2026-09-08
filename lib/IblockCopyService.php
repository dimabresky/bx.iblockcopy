<?php

namespace Bx\IblockCopy;

use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bx\IblockCopy\Copier\IblockMetaCopier;
use Bx\IblockCopy\Copier\PropertyStructureCopier;

/**
 * Public API: copy iblock metadata and property structure.
 */
final class IblockCopyService
{
    private const MODULE_ID = 'bx.iblockcopy';

    private IblockMetaCopier $metaCopier;

    private PropertyStructureCopier $propertyCopier;

    public function __construct(
        ?IblockMetaCopier $metaCopier = null,
        ?PropertyStructureCopier $propertyCopier = null
    ) {
        $this->metaCopier = $metaCopier ?? new IblockMetaCopier();
        $this->propertyCopier = $propertyCopier ?? new PropertyStructureCopier();
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

        $source = CIBlock::GetArrayByID($options->getSourceIblockId());
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

        if ($result->getErrors() === []) {
            $result->setSuccess(true);
        }

        EventManager::getInstance()->send(new Event(self::MODULE_ID, 'OnAfterIblockCopy', [
            'OPTIONS' => $options,
            'RESULT' => $result,
            'SOURCE_IBLOCK_ID' => $options->getSourceIblockId(),
            'NEW_IBLOCK_ID' => $newId,
        ]));

        Logger::info('Iblock copy finished', [
            'sourceId' => $options->getSourceIblockId(),
            'newId' => $newId,
            'success' => $result->isSuccess(),
            'errors' => count($result->getErrors()),
        ]);

        return $result;
    }
}
