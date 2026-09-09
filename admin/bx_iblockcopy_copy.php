<?php

/**
 * Admin page: prepare and run iblock structure copy.
 *
 * @global CMain $APPLICATION
 * @global CUser $USER
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bx\IblockCopy\CopyOptions;
use Bx\IblockCopy\CopyPreviewBuilder;
use Bx\IblockCopy\IblockCopyService;
use Bx\IblockCopy\UniqueCodeGenerator;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

$moduleId = 'bx.iblockcopy';

if (!$USER->IsAdmin() && $APPLICATION->GetGroupRight($moduleId) < 'W') {
    $APPLICATION->AuthForm(Loc::getMessage('BX_IBLOCKCOPY_ACCESS_DENIED') ?: 'Access denied');
}

if (!Loader::includeModule($moduleId)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    ShowError(Loc::getMessage('BX_IBLOCKCOPY_MODULE_NOT_INSTALLED') ?: 'Module is not installed.');
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

if (!Loader::includeModule('iblock')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    ShowError(Loc::getMessage('BX_IBLOCKCOPY_IBLOCK_REQUIRED') ?: 'iblock required');
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

$step = (int)($_REQUEST['step'] ?? 1);
if ($step < 1) {
    $step = 1;
}
if ($step > 2) {
    $step = 2;
}

$sourceIblockId = (int)($_REQUEST['SOURCE_IBLOCK_ID'] ?? 0);
$previewBuilder = new CopyPreviewBuilder();
$codeGenerator = new UniqueCodeGenerator();
$preview = $sourceIblockId > 0 ? $previewBuilder->build($sourceIblockId) : null;

$copyResult = null;
$errors = [];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string)($_POST['action'] ?? '') === 'copy'
    && check_bitrix_sessid()
) {
    $options = CopyOptions::fromRequest($_POST);
    if ($options->getSourceIblockId() <= 0) {
        $errors[] = Loc::getMessage('BX_IBLOCKCOPY_ERROR_SOURCE') ?: 'Select source iblock.';
    }
    if ($options->getSiteIds() === []) {
        $errors[] = Loc::getMessage('BX_IBLOCKCOPY_ERROR_SITES') ?: 'Select sites.';
    }
    if ($options->getName() === '') {
        $errors[] = Loc::getMessage('BX_IBLOCKCOPY_ERROR_NAME') ?: 'Name is required.';
    }

    if ($errors === []) {
        $service = new IblockCopyService();
        $copyResult = $service->copy($options);
        if (!$copyResult->isSuccess()) {
            $errors = array_merge($errors, $copyResult->getErrors());
            $step = 2;
            $preview = $previewBuilder->build($options->getSourceIblockId());
        }
    } else {
        $step = 2;
        $preview = $previewBuilder->build($sourceIblockId);
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string)($_POST['action'] ?? '') === 'prepare'
    && check_bitrix_sessid()
) {
    if ($sourceIblockId <= 0 || $preview === null) {
        $errors[] = Loc::getMessage('BX_IBLOCKCOPY_ERROR_SOURCE') ?: 'Select source iblock.';
        $step = 1;
    } else {
        $step = 2;
    }
}

$APPLICATION->SetTitle(Loc::getMessage('BX_IBLOCKCOPY_PAGE_TITLE') ?: 'Iblock copy');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$iblockList = [];
$iblockIterator = CIBlock::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], [], false);
while ($row = $iblockIterator->Fetch()) {
    $iblockList[(int)$row['ID']] = '[' . $row['ID'] . '] ' . $row['NAME'];
}

$typeList = [];
$typeIterator = CIBlockType::GetList(['SORT' => 'ASC', 'ID' => 'ASC']);
while ($type = $typeIterator->Fetch()) {
    $lang = CIBlockType::GetByIDLang($type['ID'], LANGUAGE_ID);
    $typeList[$type['ID']] = '[' . $type['ID'] . '] ' . (is_array($lang) ? $lang['NAME'] : $type['ID']);
}

$siteList = [];
$siteIterator = CSite::GetList('sort', 'asc', ['ACTIVE' => 'Y']);
while ($site = $siteIterator->Fetch()) {
    $siteList[$site['LID']] = '[' . $site['LID'] . '] ' . $site['NAME'];
}

$formDefaults = [
    'IBLOCK_TYPE_ID' => is_array($preview) ? (string)$preview['IBLOCK_TYPE_ID'] : '',
    'LID' => is_array($preview) ? $preview['LID'] : [],
    'NAME' => is_array($preview) ? ((string)$preview['NAME'] . ' (копия)') : '',
    'CODE' => is_array($preview)
        ? $codeGenerator->suggestCode((string)$preview['CODE'], 'CODE')
        : '',
    'API_CODE' => is_array($preview)
        ? $codeGenerator->suggestCode(
            (string)($preview['API_CODE'] !== '' ? $preview['API_CODE'] : $preview['CODE']),
            'API_CODE'
        )
        : '',
    'XML_ID' => is_array($preview)
        ? $codeGenerator->suggestCode(
            (string)($preview['XML_ID'] !== '' ? $preview['XML_ID'] : $preview['CODE']),
            'XML_ID'
        )
        : '',
    'ACTIVE' => is_array($preview) ? (string)$preview['ACTIVE'] : 'Y',
    'COPY_PICTURE' => 'Y',
    'COPY_URL_TEMPLATES' => 'Y',
    'COPY_SEO_TEMPLATES' => 'Y',
    'COPY_GROUP_RIGHTS' => 'Y',
    'COPY_FIELD_SETTINGS' => 'Y',
    'COPY_PROPERTIES' => 'Y',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'copy') {
    foreach (array_keys($formDefaults) as $key) {
        if ($key === 'LID') {
            $sites = $_POST['LID'] ?? [];
            $formDefaults['LID'] = is_array($sites) ? $sites : [$sites];
            continue;
        }
        if (isset($_POST[$key])) {
            $formDefaults[$key] = $_POST[$key];
        }
    }
}

if ($copyResult !== null && $copyResult->isSuccess()) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => str_replace(
            '#ID#',
            (string)$copyResult->getNewIblockId(),
            Loc::getMessage('BX_IBLOCKCOPY_SUCCESS') ?: 'Copied. New ID: #ID#'
        ),
        'TYPE' => 'OK',
        'HTML' => true,
        'DETAILS' => '<a href="iblock_edit.php?ID=' . (int)$copyResult->getNewIblockId()
            . '&type=' . urlencode((string)$formDefaults['IBLOCK_TYPE_ID'])
            . '&lang=' . LANGUAGE_ID . '&admin=Y">'
            . htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_OPEN_IBLOCK') ?: 'Open')
            . '</a>',
    ]);

    if ($copyResult->getWarnings() !== []) {
        CAdminMessage::ShowMessage([
            'MESSAGE' => Loc::getMessage('BX_IBLOCKCOPY_WARNINGS') ?: 'Warnings',
            'TYPE' => 'OK',
            'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $copyResult->getWarnings())),
            'HTML' => true,
        ]);
    }
}

if ($errors !== []) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => Loc::getMessage('BX_IBLOCKCOPY_ERRORS') ?: 'Errors',
        'TYPE' => 'ERROR',
        'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $errors)),
        'HTML' => true,
    ]);
}

if ($copyResult !== null && $copyResult->getWarnings() !== [] && !$copyResult->isSuccess()) {
    CAdminMessage::ShowMessage([
        'MESSAGE' => Loc::getMessage('BX_IBLOCKCOPY_WARNINGS') ?: 'Warnings',
        'TYPE' => 'ERROR',
        'DETAILS' => implode('<br>', array_map('htmlspecialcharsbx', $copyResult->getWarnings())),
        'HTML' => true,
    ]);
}

$aTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('BX_IBLOCKCOPY_PAGE_TITLE') ?: 'Iblock copy',
        'TITLE' => $step === 1
            ? (Loc::getMessage('BX_IBLOCKCOPY_STEP1_TITLE') ?: 'Step 1')
            : (Loc::getMessage('BX_IBLOCKCOPY_STEP2_TITLE') ?: 'Step 2'),
    ],
];
$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>

<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="step" value="<?= (int)$step ?>">
    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab(); ?>

    <?php if ($step === 1): ?>
        <tr>
            <td width="40%"><label for="SOURCE_IBLOCK_ID"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_SOURCE_IBLOCK') ?: 'Source') ?>:</label></td>
            <td width="60%">
                <select name="SOURCE_IBLOCK_ID" id="SOURCE_IBLOCK_ID" required>
                    <option value="0"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_SELECT') ?: 'Select') ?></option>
                    <?php foreach ($iblockList as $id => $label): ?>
                        <option value="<?= (int)$id ?>"<?= $sourceIblockId === (int)$id ? ' selected' : '' ?>>
                            <?= htmlspecialcharsbx($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <input type="hidden" name="action" value="prepare">
    <?php else: ?>
        <input type="hidden" name="SOURCE_IBLOCK_ID" value="<?= (int)$sourceIblockId ?>">
        <input type="hidden" name="action" value="copy">

        <?php if (is_array($preview)): ?>
            <tr class="heading">
                <td colspan="2"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_PREVIEW') ?: 'Preview') ?></td>
            </tr>
            <tr>
                <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_PREVIEW_TYPE') ?: 'Type') ?>:</td>
                <td><?= htmlspecialcharsbx((string)$preview['IBLOCK_TYPE_ID']) ?></td>
            </tr>
            <tr>
                <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_PREVIEW_VERSION') ?: 'Version') ?>:</td>
                <td><?= (int)$preview['VERSION'] ?></td>
            </tr>
            <tr>
                <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_PREVIEW_PROPERTIES') ?: 'Properties') ?>:</td>
                <td><?= (int)$preview['PROPERTY_COUNT'] ?></td>
            </tr>
            <tr>
                <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_PREVIEW_LISTS') ?: 'Lists') ?>:</td>
                <td><?= (int)$preview['LIST_PROPERTY_COUNT'] ?></td>
            </tr>
            <tr>
                <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_PREVIEW_DIRECTORIES') ?: 'Directories') ?>:</td>
                <td><?= (int)$preview['DIRECTORY_PROPERTY_COUNT'] ?></td>
            </tr>
            <tr>
                <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_PREVIEW_LINKS') ?: 'Links') ?>:</td>
                <td><?= (int)$preview['LINK_PROPERTY_COUNT'] ?></td>
            </tr>
            <tr>
                <td colspan="2">
                    <p><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_DIRECTORY_NOTE') ?: '') ?></p>
                    <table class="internal" width="100%">
                        <tr class="heading">
                            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COL_NAME') ?: 'Name') ?></td>
                            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COL_CODE') ?: 'Code') ?></td>
                            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COL_TYPE') ?: 'Type') ?></td>
                            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COL_ENUM') ?: 'Enums') ?></td>
                            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COL_DIR') ?: 'HL') ?></td>
                            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COL_LINK') ?: 'Link') ?></td>
                        </tr>
                        <?php foreach ($preview['PROPERTIES'] as $property): ?>
                            <tr>
                                <td><?= htmlspecialcharsbx((string)$property['NAME']) ?></td>
                                <td><?= htmlspecialcharsbx((string)$property['CODE']) ?></td>
                                <td>
                                    <?= htmlspecialcharsbx((string)$property['PROPERTY_TYPE']) ?>
                                    <?php if ($property['USER_TYPE'] !== ''): ?>
                                        / <?= htmlspecialcharsbx((string)$property['USER_TYPE']) ?>
                                    <?php endif; ?>
                                    <?php if ($property['MULTIPLE'] === 'Y'): ?> [M]<?php endif; ?>
                                </td>
                                <td><?= (int)$property['ENUM_COUNT'] ?></td>
                                <td><?= htmlspecialcharsbx((string)$property['DIRECTORY_TABLE']) ?></td>
                                <td><?= (int)$property['LINK_IBLOCK_ID'] > 0 ? (int)$property['LINK_IBLOCK_ID'] : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
            </tr>
        <?php endif; ?>

        <tr class="heading">
            <td colspan="2"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_STEP2_TITLE') ?: 'Target settings') ?></td>
        </tr>
        <tr>
            <td><label for="IBLOCK_TYPE_ID"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_TARGET_TYPE') ?: 'Type') ?>:</label></td>
            <td>
                <select name="IBLOCK_TYPE_ID" id="IBLOCK_TYPE_ID">
                    <?php foreach ($typeList as $typeId => $label): ?>
                        <option value="<?= htmlspecialcharsbx((string)$typeId) ?>"<?= (string)$formDefaults['IBLOCK_TYPE_ID'] === (string)$typeId ? ' selected' : '' ?>>
                            <?= htmlspecialcharsbx($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_TARGET_SITES') ?: 'Sites') ?>:</td>
            <td>
                <?php foreach ($siteList as $lid => $label): ?>
                    <label style="display:block;margin-bottom:4px;">
                        <input type="checkbox" name="LID[]" value="<?= htmlspecialcharsbx((string)$lid) ?>"
                            <?= in_array((string)$lid, array_map('strval', (array)$formDefaults['LID']), true) ? ' checked' : '' ?>>
                        <?= htmlspecialcharsbx($label) ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <td><label for="NAME"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_TARGET_NAME') ?: 'Name') ?>:</label></td>
            <td><input type="text" name="NAME" id="NAME" size="50" value="<?= htmlspecialcharsbx((string)$formDefaults['NAME']) ?>" required></td>
        </tr>
        <tr>
            <td><label for="CODE"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_TARGET_CODE') ?: 'Code') ?>:</label></td>
            <td><input type="text" name="CODE" id="CODE" size="50" value="<?= htmlspecialcharsbx((string)$formDefaults['CODE']) ?>"></td>
        </tr>
        <tr>
            <td><label for="API_CODE"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_TARGET_API_CODE') ?: 'API_CODE') ?>:</label></td>
            <td><input type="text" name="API_CODE" id="API_CODE" size="50" maxlength="50" value="<?= htmlspecialcharsbx((string)$formDefaults['API_CODE']) ?>"></td>
        </tr>
        <tr>
            <td><label for="XML_ID"><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_TARGET_XML_ID') ?: 'XML_ID') ?>:</label></td>
            <td><input type="text" name="XML_ID" id="XML_ID" size="50" value="<?= htmlspecialcharsbx((string)$formDefaults['XML_ID']) ?>"></td>
        </tr>
        <tr>
            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_TARGET_ACTIVE') ?: 'Active') ?>:</td>
            <td>
                <select name="ACTIVE">
                    <option value="Y"<?= $formDefaults['ACTIVE'] === 'Y' ? ' selected' : '' ?>><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_YES') ?: 'Y') ?></option>
                    <option value="N"<?= $formDefaults['ACTIVE'] === 'N' ? ' selected' : '' ?>><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_NO') ?: 'N') ?></option>
                </select>
            </td>
        </tr>
        <?php
        $checkboxes = [
            'COPY_PICTURE' => Loc::getMessage('BX_IBLOCKCOPY_COPY_PICTURE') ?: 'Picture',
            'COPY_URL_TEMPLATES' => Loc::getMessage('BX_IBLOCKCOPY_COPY_URL') ?: 'URL templates',
            'COPY_SEO_TEMPLATES' => Loc::getMessage('BX_IBLOCKCOPY_COPY_SEO') ?: 'SEO',
            'COPY_GROUP_RIGHTS' => Loc::getMessage('BX_IBLOCKCOPY_COPY_RIGHTS') ?: 'Rights',
            'COPY_FIELD_SETTINGS' => Loc::getMessage('BX_IBLOCKCOPY_COPY_FIELDS') ?: 'Fields',
            'COPY_PROPERTIES' => Loc::getMessage('BX_IBLOCKCOPY_COPY_PROPERTIES') ?: 'Properties',
        ];
        foreach ($checkboxes as $name => $label):
            ?>
            <tr>
                <td><?= htmlspecialcharsbx((string)$label) ?>:</td>
                <td>
                    <input type="hidden" name="<?= htmlspecialcharsbx($name) ?>" value="N">
                    <input type="checkbox" name="<?= htmlspecialcharsbx($name) ?>" value="Y"
                        <?= ($formDefaults[$name] ?? 'Y') === 'Y' ? ' checked' : '' ?>>
                    <?php if ($name === 'COPY_URL_TEMPLATES'): ?>
                        <br><small><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COPY_URL_HINT') ?: '') ?></small>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COPY_SECTIONS') ?: 'Sections') ?>:</td>
            <td>
                <input type="checkbox" disabled>
                <small><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_NOT_AVAILABLE') ?: 'v2') ?></small>
            </td>
        </tr>
        <tr>
            <td><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_COPY_ELEMENTS') ?: 'Elements') ?>:</td>
            <td>
                <input type="checkbox" disabled>
                <small><?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_NOT_AVAILABLE') ?: 'v2') ?></small>
            </td>
        </tr>
    <?php endif; ?>

    <?php
    $tabControl->Buttons();
    if ($step === 1) {
        ?>
        <input type="submit" class="adm-btn-save" value="<?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_NEXT') ?: 'Next') ?>">
        <?php
    } else {
        ?>
        <input type="submit" class="adm-btn-save" value="<?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_RUN') ?: 'Copy') ?>">
        <a class="adm-btn" href="<?= htmlspecialcharsbx($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&SOURCE_IBLOCK_ID=' . (int)$sourceIblockId . '&step=1') ?>">
            <?= htmlspecialcharsbx(Loc::getMessage('BX_IBLOCKCOPY_BACK') ?: 'Back') ?>
        </a>
        <?php
    }
    $tabControl->End();
    ?>
</form>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
