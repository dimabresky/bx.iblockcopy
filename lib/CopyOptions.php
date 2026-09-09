<?php

namespace Bx\IblockCopy;

/**
 * Input options for iblock structure copy.
 */
final class CopyOptions
{
    private int $sourceIblockId = 0;

    private string $iblockTypeId = '';

    /** @var list<string> */
    private array $siteIds = [];

    private string $name = '';

    private string $code = '';

    private string $apiCode = '';

    private string $xmlId = '';

    private string $active = 'Y';

    private bool $copyPicture = true;

    private bool $copyUrlTemplates = true;

    private bool $copySeoTemplates = true;

    private bool $copyGroupRights = true;

    private bool $copyFieldSettings = true;

    private bool $copyProperties = true;

    private bool $copyElementFormSettings = true;

    private bool $copySectionUserFields = true;

    public function getSourceIblockId(): int
    {
        return $this->sourceIblockId;
    }

    public function setSourceIblockId(int $sourceIblockId): self
    {
        $this->sourceIblockId = $sourceIblockId;

        return $this;
    }

    public function getIblockTypeId(): string
    {
        return $this->iblockTypeId;
    }

    public function setIblockTypeId(string $iblockTypeId): self
    {
        $this->iblockTypeId = $iblockTypeId;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSiteIds(): array
    {
        return $this->siteIds;
    }

    /**
     * @param list<string> $siteIds
     */
    public function setSiteIds(array $siteIds): self
    {
        $this->siteIds = array_values(array_filter(array_map('strval', $siteIds)));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getApiCode(): string
    {
        return $this->apiCode;
    }

    public function setApiCode(string $apiCode): self
    {
        $this->apiCode = $apiCode;

        return $this;
    }

    public function getXmlId(): string
    {
        return $this->xmlId;
    }

    public function setXmlId(string $xmlId): self
    {
        $this->xmlId = $xmlId;

        return $this;
    }

    public function getActive(): string
    {
        return $this->active === 'N' ? 'N' : 'Y';
    }

    public function setActive(string $active): self
    {
        $this->active = $active === 'N' ? 'N' : 'Y';

        return $this;
    }

    public function isCopyPicture(): bool
    {
        return $this->copyPicture;
    }

    public function setCopyPicture(bool $copyPicture): self
    {
        $this->copyPicture = $copyPicture;

        return $this;
    }

    public function isCopyUrlTemplates(): bool
    {
        return $this->copyUrlTemplates;
    }

    public function setCopyUrlTemplates(bool $copyUrlTemplates): self
    {
        $this->copyUrlTemplates = $copyUrlTemplates;

        return $this;
    }

    public function isCopySeoTemplates(): bool
    {
        return $this->copySeoTemplates;
    }

    public function setCopySeoTemplates(bool $copySeoTemplates): self
    {
        $this->copySeoTemplates = $copySeoTemplates;

        return $this;
    }

    public function isCopyGroupRights(): bool
    {
        return $this->copyGroupRights;
    }

    public function setCopyGroupRights(bool $copyGroupRights): self
    {
        $this->copyGroupRights = $copyGroupRights;

        return $this;
    }

    public function isCopyFieldSettings(): bool
    {
        return $this->copyFieldSettings;
    }

    public function setCopyFieldSettings(bool $copyFieldSettings): self
    {
        $this->copyFieldSettings = $copyFieldSettings;

        return $this;
    }

    public function isCopyProperties(): bool
    {
        return $this->copyProperties;
    }

    public function setCopyProperties(bool $copyProperties): self
    {
        $this->copyProperties = $copyProperties;

        return $this;
    }

    public function isCopyElementFormSettings(): bool
    {
        return $this->copyElementFormSettings;
    }

    public function setCopyElementFormSettings(bool $copyElementFormSettings): self
    {
        $this->copyElementFormSettings = $copyElementFormSettings;

        return $this;
    }

    public function isCopySectionUserFields(): bool
    {
        return $this->copySectionUserFields;
    }

    public function setCopySectionUserFields(bool $copySectionUserFields): self
    {
        $this->copySectionUserFields = $copySectionUserFields;

        return $this;
    }

    /**
     * @param array<string, mixed> $request
     */
    public static function fromRequest(array $request): self
    {
        $options = new self();
        $options->setSourceIblockId((int)($request['SOURCE_IBLOCK_ID'] ?? 0));
        $options->setIblockTypeId(trim((string)($request['IBLOCK_TYPE_ID'] ?? '')));
        $sites = $request['LID'] ?? [];
        if (!is_array($sites)) {
            $sites = [$sites];
        }
        $options->setSiteIds($sites);
        $options->setName(trim((string)($request['NAME'] ?? '')));
        $options->setCode(trim((string)($request['CODE'] ?? '')));
        $options->setApiCode(trim((string)($request['API_CODE'] ?? '')));
        $options->setXmlId(trim((string)($request['XML_ID'] ?? '')));
        $options->setActive((string)($request['ACTIVE'] ?? 'Y'));
        $options->setCopyPicture(($request['COPY_PICTURE'] ?? 'Y') === 'Y');
        $options->setCopyUrlTemplates(($request['COPY_URL_TEMPLATES'] ?? 'Y') === 'Y');
        $options->setCopySeoTemplates(($request['COPY_SEO_TEMPLATES'] ?? 'Y') === 'Y');
        $options->setCopyGroupRights(($request['COPY_GROUP_RIGHTS'] ?? 'Y') === 'Y');
        $options->setCopyFieldSettings(($request['COPY_FIELD_SETTINGS'] ?? 'Y') === 'Y');
        $options->setCopyProperties(($request['COPY_PROPERTIES'] ?? 'Y') === 'Y');
        $options->setCopyElementFormSettings(($request['COPY_ELEMENT_FORM_SETTINGS'] ?? 'Y') === 'Y');
        $options->setCopySectionUserFields(($request['COPY_SECTION_USER_FIELDS'] ?? 'Y') === 'Y');

        return $options;
    }
}
