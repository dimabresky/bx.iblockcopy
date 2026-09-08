# bx.iblockcopy

Локальный модуль **1C-Bitrix** для копирования **структуры инфоблока**: карточки (включая SEO-метаданные), свойств и значений списков. Разделы и элементы в текущей версии **не копируются**.

- **Репозиторий:** [github.com/dimabresky/bx.iblockcopy](https://github.com/dimabresky/bx.iblockcopy)
- **Разработчик:** [dimabresky](https://github.com/dimabresky)
- **Версия:** 1.0.0

---

## Возможности (v1)

- Копирование карточки инфоблока: тип, сайты, `NAME` / `CODE` / `API_CODE` / `XML_ID`, активность, сортировка, версия хранения свойств, описание, картинка, URL-шаблоны, RSS и индексация.
- SEO-шаблоны инфоблока (`IPROPERTY_TEMPLATES`).
- Права групп (`GROUP_ID`) и расширенные права (`CIBlockRights`), если у источника режим `E`.
- Настройки полей элемента и раздела (`CIBlock::GetFields` / `SetFields`).
- Все свойства: тип, пользовательский тип, `USER_TYPE_SETTINGS`, `LINK_IBLOCK_ID`, множественность, обязательность, подсказка, значение по умолчанию и прочие параметры `CIBlockProperty`.
- Значения свойств типа «Список» (`VALUE`, `XML_ID`, `DEF`, `SORT`).
- Свойства типа «Справочник» (`directory`): **переиспользуется** существующий Highload-блок (`USER_TYPE_SETTINGS.TABLE_NAME` без клонирования таблицы и записей).
- Двухшаговая админ-страница подготовки: предпросмотр источника → настройки копии → запуск.
- При ошибке копирования свойств созданный инфоблок **удаляется** (rollback), чтобы не оставлять неполные копии.

**Не копируется в v1:** разделы, элементы, торговый каталог / SKU, бизнес-процессы, клон Highload-блока.

---

## Требования

| Компонент | Требование |
|-----------|------------|
| Bitrix | Управление сайтом / Интернет-магазин, модуль `iblock` |
| PHP | 8.1+ |
| Права | запись в админке модуля (`W`) для запуска копирования |

---

## Установка

1. Разместите каталог модуля в `local/modules/bx.iblockcopy/`.

   Как git submodule в корне сайта:

   ```bash
   git submodule add https://github.com/dimabresky/bx.iblockcopy.git local/modules/bx.iblockcopy
   git submodule update --init --recursive
   ```

2. В админке: **Настройки → Настройки продукта → Модули** → установите **«Копирование инфоблока»** (`bx.iblockcopy`).

3. При установке модуль:
   - регистрируется в системе;
   - копирует админ-скрипт в `/bitrix/admin/bx_iblockcopy_copy.php`;
   - регистрирует пункт меню через `OnBuildGlobalMenu`.

Собственных таблиц БД модуль не создаёт.

---

## Работа в админке

Путь: **Контент → Копирование инфоблока**  
(или `/bitrix/admin/bx_iblockcopy_copy.php`).

Страница доступна администраторам и пользователям с правом модуля **«Запись»** (`W`).

### Шаг 1. Выбор источника

Выберите исходный инфоблок и нажмите **Далее**. Модуль покажет предпросмотр:

- тип и версия хранения свойств;
- число свойств, списков, справочников и привязок (`E`/`G`);
- таблица свойств: код, тип, число значений списка, `TABLE_NAME` справочника, `LINK_IBLOCK_ID`.

### Шаг 2. Настройки копии

| Поле | По умолчанию |
|------|----------------|
| Тип инфоблока | как у источника |
| Сайты | как у источника |
| Название | `{NAME} (копия)` |
| `CODE` / `API_CODE` / `XML_ID` | суффикс `_copy` / `Copy` с проверкой уникальности |
| Активность | как у источника |
| Картинка, URL-шаблоны, SEO, права, настройки полей, свойства | включено |

URL-шаблоны копируются **как есть** — проверьте коллизии ЧПУ на целевом сайте.

Чекбоксы «Копировать разделы» и «Копировать элементы» отключены: это следующая итерация модуля.

### Результат

При успехе отображается ID нового инфоблока и ссылка на его карточку.  
Если копирование свойств падает, новый инфоблок удаляется, на странице показываются ошибки и предупреждение о rollback.

---

## Права модуля

**Настройки → Пользователи → Уровни доступа** (модуль `bx.iblockcopy`):

| Код | Уровень |
|-----|---------|
| `D` | Закрыт |
| `R` | Чтение (пункт меню виден) |
| `W` | Запись (запуск копирования) |

---

## PHP API

```php
use Bitrix\Main\Loader;
use Bx\IblockCopy\CopyOptions;
use Bx\IblockCopy\IblockCopyService;

Loader::includeModule('bx.iblockcopy');

$options = (new CopyOptions())
    ->setSourceIblockId(12)
    ->setIblockTypeId('content')
    ->setSiteIds(['s1'])
    ->setName('Новости (копия)')
    ->setCode('news_copy')
    ->setApiCode('NewsCopy')
    ->setCopyPicture(true)
    ->setCopyUrlTemplates(true)
    ->setCopySeoTemplates(true)
    ->setCopyGroupRights(true)
    ->setCopyFieldSettings(true)
    ->setCopyProperties(true);

$result = (new IblockCopyService())->copy($options);

if ($result->isSuccess()) {
    $newId = $result->getNewIblockId();
    $propertyMap = $result->getPropertyMap(); // old property ID => new ID
    $enumMap = $result->getEnumMap();         // old enum ID => new ID
} else {
    $errors = $result->getErrors();
}

$warnings = $result->getWarnings();
```

Опции также можно собрать из массива запроса: `CopyOptions::fromRequest($request)`.

---

## События

Модуль отправляет события `bx.iblockcopy`:

| Событие | Когда | Полезные параметры |
|---------|--------|--------------------|
| `OnBeforeIblockCopy` | до создания копии | `OPTIONS`, `SOURCE` |
| `OnAfterIblockCopy` | после завершения (в т.ч. rollback) | `RESULT`, `NEW_IBLOCK_ID`, `ROLLED_BACK` |
| `OnBeforePropertyCopy` | перед добавлением свойства | `FIELDS`, `SOURCE_PROPERTY` |
| `OnAfterPropertyCopy` | после добавления свойства | `SOURCE_PROPERTY_ID`, `NEW_PROPERTY_ID` |

Обработчик `OnBeforeIblockCopy` может вернуть `EventResult::ERROR`, чтобы отменить копирование целиком.  
`OnBeforePropertyCopy` с `EventResult::ERROR` **пропускает только это свойство**; в `FIELDS` можно подменить данные перед `CIBlockProperty::Add`.

---

## Удаление

**Настройки → Модули** → удалите `bx.iblockcopy`. Модуль снимет обработчики меню и удалит файл `/bitrix/admin/bx_iblockcopy_copy.php`. Скопированные инфоблоки **не** удаляются.

---

## Планы

Следующая итерация: копирование разделов и элементов.
