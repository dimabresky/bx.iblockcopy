<?php

namespace Bx\IblockCopy;

/**
 * Result of an iblock structure copy operation.
 */
final class CopyResult
{
    private bool $success = false;

    private int $sourceIblockId = 0;

    private int $newIblockId = 0;

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<int, int> old property ID => new property ID */
    private array $propertyMap = [];

    /** @var array<int, int> old enum ID => new enum ID */
    private array $enumMap = [];

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;

        return $this;
    }

    public function getSourceIblockId(): int
    {
        return $this->sourceIblockId;
    }

    public function setSourceIblockId(int $sourceIblockId): self
    {
        $this->sourceIblockId = $sourceIblockId;

        return $this;
    }

    public function getNewIblockId(): int
    {
        return $this->newIblockId;
    }

    public function setNewIblockId(int $newIblockId): self
    {
        $this->newIblockId = $newIblockId;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addError(string $error): self
    {
        $this->errors[] = $error;
        $this->success = false;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getPropertyMap(): array
    {
        return $this->propertyMap;
    }

    /**
     * @param array<int, int> $propertyMap
     */
    public function setPropertyMap(array $propertyMap): self
    {
        $this->propertyMap = $propertyMap;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getEnumMap(): array
    {
        return $this->enumMap;
    }

    /**
     * @param array<int, int> $enumMap
     */
    public function setEnumMap(array $enumMap): self
    {
        $this->enumMap = $enumMap;

        return $this;
    }
}
