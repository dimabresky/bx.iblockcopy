<?php

namespace Bx\IblockCopy;

use Bitrix\Main\Loader;

/**
 * Generates unique CODE / API_CODE / XML_ID for a new iblock.
 */
final class UniqueCodeGenerator
{
    private const MAX_ATTEMPTS = 50;

    public function suggestCode(string $base, string $field = 'CODE'): string
    {
        $base = $this->normalize($base, $field);
        if ($base === '') {
            $base = $field === 'API_CODE' ? 'IblockCopy' : 'iblock_copy';
        }

        $candidate = $base . ($field === 'API_CODE' ? 'Copy' : '_copy');
        if (!$this->exists($candidate, $field)) {
            return $candidate;
        }

        for ($i = 2; $i <= self::MAX_ATTEMPTS; ++$i) {
            $next = $candidate . $i;
            if (!$this->exists($next, $field)) {
                return $next;
            }
        }

        return $candidate . '_' . time();
    }

    public function ensureUnique(string $value, string $field): string
    {
        $value = $this->normalize($value, $field);
        if ($value === '') {
            return $this->suggestCode('', $field);
        }

        if (!$this->exists($value, $field)) {
            return $value;
        }

        return $this->suggestCode($value, $field);
    }

    public function isIblockTypeValid(string $typeId): bool
    {
        if ($typeId === '' || !Loader::includeModule('iblock')) {
            return false;
        }

        $row = CIBlockType::GetByID($typeId)->Fetch();

        return is_array($row);
    }

    private function exists(string $value, string $field): bool
    {
        if ($value === '' || !Loader::includeModule('iblock')) {
            return false;
        }

        $filterField = match ($field) {
            'API_CODE' => '=API_CODE',
            'XML_ID' => '=XML_ID',
            default => '=CODE',
        };

        $row = CIBlock::GetList([], [$filterField => $value], false)->Fetch();

        return is_array($row);
    }

    private function normalize(string $value, string $field): string
    {
        $value = trim($value);
        if ($field === 'API_CODE') {
            $value = preg_replace('/[^A-Za-z0-9_]/', '', $value) ?? '';
            if ($value !== '' && preg_match('/^[0-9]/', $value)) {
                $value = 'A' . $value;
            }

            return $value;
        }

        $value = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $value) ?? '';

        return trim($value, '_');
    }
}
