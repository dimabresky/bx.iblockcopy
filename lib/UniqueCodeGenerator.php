<?php

namespace Bx\IblockCopy;

use Bitrix\Main\Loader;

/**
 * Generates unique CODE / API_CODE / XML_ID for a new iblock.
 *
 * API_CODE rules (Bitrix): 1–50 Latin letters/digits, must start with a letter.
 */
final class UniqueCodeGenerator
{
    private const MAX_ATTEMPTS = 50;

    private const API_CODE_MAX_LENGTH = 50;

    public function suggestCode(string $base, string $field = 'CODE'): string
    {
        $base = $this->normalize($base, $field);
        if ($base === '') {
            $base = $field === 'API_CODE' ? 'IblockCopy' : 'iblock_copy';
        }

        $suffix = $field === 'API_CODE' ? 'Copy' : '_copy';
        $candidate = $this->withSuffix($base, $suffix, $field);
        if (!$this->exists($candidate, $field)) {
            return $candidate;
        }

        for ($i = 2; $i <= self::MAX_ATTEMPTS; ++$i) {
            $next = $this->withSuffix($base, $suffix . $i, $field);
            if (!$this->exists($next, $field)) {
                return $next;
            }
        }

        $fallbackSuffix = $field === 'API_CODE'
            ? 'C' . substr((string)time(), -8)
            : '_' . time();

        return $this->withSuffix($base, $fallbackSuffix, $field);
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

        $row = \CIBlockType::GetByID($typeId)->Fetch();

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

        $row = \CIBlock::GetList([], [$filterField => $value], false)->Fetch();

        return is_array($row);
    }

    private function normalize(string $value, string $field): string
    {
        $value = trim($value);
        if ($field === 'API_CODE') {
            // Strip non-alphanumeric (including underscores from CODE-like bases).
            $value = preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '';
            if ($value !== '' && preg_match('/^[0-9]/', $value)) {
                $value = 'A' . $value;
            }

            if (strlen($value) > self::API_CODE_MAX_LENGTH) {
                $value = substr($value, 0, self::API_CODE_MAX_LENGTH);
            }

            return $value;
        }

        $value = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function withSuffix(string $base, string $suffix, string $field): string
    {
        if ($field !== 'API_CODE') {
            return $base . $suffix;
        }

        $suffix = preg_replace('/[^A-Za-z0-9]/', '', $suffix) ?? '';
        if ($suffix === '') {
            $suffix = 'Copy';
        }

        $maxBaseLength = self::API_CODE_MAX_LENGTH - strlen($suffix);
        if ($maxBaseLength < 1) {
            return substr($suffix, 0, self::API_CODE_MAX_LENGTH);
        }

        if (strlen($base) > $maxBaseLength) {
            $base = substr($base, 0, $maxBaseLength);
        }

        $result = $base . $suffix;
        if ($result !== '' && preg_match('/^[0-9]/', $result)) {
            $result = 'A' . substr($result, 0, self::API_CODE_MAX_LENGTH - 1);
        }

        return $result;
    }
}
