# IMPLEMENTATION PLAN: Теги для дневниковых записей (v0.0.3)

## Обзор

Добавление системы тегов для организации и фильтрации записей дневника. Пользователи смогут:
1. Добавлять теги к записям (через inline #hashtag или отдельное поле)
2. Видеть облако тегов в правой боковой панели
3. Фильтровать записи по выбранному тегу
4. Искать записи по названию тега

**Текущее состояние**: приложение поддерживает несколько записей в день с автоматическим ID (v0.0.2)
**Новое состояние**: полная поддержка тегов с облаком и фильтрацией (v0.0.3)

---

## Архитектурные решения

### Хранение тегов: Many-to-Many через отдельную таблицу

**Выбор**: Отдельная таблица `diary_tags` + таблица связей `diary_entry_tags`

**Обоснование**:
- **Нормализация**: избегаем дублирования тегов в каждой записи
- **Производительность**: быстрый поиск записей по тегу через индекс
- **Масштабируемость**: легко добавить метаданные тега (цвет, частота, дата создания)
- **Запросы**: простые SQL запросы для облака тегов и фильтрации

**Альтернативы отклонены**:
- JSON в entry_content: невозможно искать по тегам без парсинга
- Comma-separated в отдельной колонке: сложно нормализовать, дублирование

### Автоматизация извлечения тегов

**Подход**: Inline #hashtag parsing + опциональное поле `tags`

**Алгоритм**:
1. Пользователь пишет в markdown-редакторе: "Отличный день #work #health #travel"
2. На сохранение (PUT /entry/:id) бэкенд:
   - Парсит содержимое regex `/(?:^|\s)#([a-zA-Z0-9_-]+)/g`
   - Извлекает: `['work', 'health', 'travel']`
   - Создаёт/использует существующие теги в `diary_tags`
   - Обновляет связи в `diary_entry_tags`
   - Возвращает массив тегов в ответе API

**Преимущества**:
- Естественный UX (привычный как Twitter/Instagram)
- Не требует отдельного UI для добавления тегов
- Теги остаются в markdown-контексте
- Легко искать по тегам в самом тексте

### Размещение облака тегов

**Решение**: Правая боковая панель (новый компонент TagCloud.vue)

**Текущая структура**:
```
Diary.vue (NcAppNavigation на ЛЕВОЙ стороне)
└─ Календарь, меню записей, экспорт (слева)
```

**Новая структура**:
```
Diary.vue
├─ NcAppNavigation (ЛЕВАЯ боковая панель) — текущее содержимое
├─ NcAppContent (основная область)
│  └─ router-view (DayView/Editor)
└─ [НОВОЕ] NcAppSidebar (ПРАВАЯ боковая панель) — облако тегов
   ├─ TagCloud.vue (облако тегов)
   ├─ TagSearch.vue (поиск по тегам)
   └─ FilterPanel.vue (активные фильтры)
```

**UX Flow**:
1. Открыть запись любым способом
2. Теги автоматически извлекаются и показываются в облаке (правая панель)
3. Клик на тег в облаке → фильтр записей по этому тегу
4. В фильтре видно "Тег: #work (15 записей)" с кнопкой ✕ для сброса

### Парсинг и валидация тегов

**Правила валидации**:
- Длина: 1-50 символов
- Символы: `a-z`, `A-Z`, `0-9`, `_`, `-` (без пробелов и спецсимволов)
- Регистр: нормализуется в lowercase (`#Work` → `#work`)
- Дубликаты: автоматически удаляются в одной записи

**Regex на бэкенде** (PHP):
```php
preg_match_all('/#([a-zA-Z0-9_-]+)/u', $content, $matches);
$tags = array_unique(array_map('strtolower', $matches[1] ?? []));
```

---

## Схема базы данных

### Таблица `diary_tags`

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | INTEGER AUTO_INCREMENT | PRIMARY KEY |
| `uid` | VARCHAR(64) NOT NULL | Владелец тега (scoped по пользователю) |
| `tag_name` | VARCHAR(50) NOT NULL | Название тега (lowercase) |
| `created_at` | DATETIME NOT NULL | Дата создания |

**Индексы**:
1. PRIMARY KEY (`id`)
2. UNIQUE (`uid`, `tag_name`) — уникальность тега для каждого пользователя
3. INDEX (`uid`, `created_at` DESC) — облако тегов в хронологическом порядке

### Таблица `diary_entry_tags`

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | INTEGER AUTO_INCREMENT | PRIMARY KEY |
| `entry_id` | INTEGER NOT NULL FK | Ссылка на запись (diary.id) |
| `tag_id` | INTEGER NOT NULL FK | Ссылка на тег (diary_tags.id) |

**Индексы**:
1. PRIMARY KEY (`id`)
2. UNIQUE (`entry_id`, `tag_id`) — предотвращение дубликатов
3. INDEX (`tag_id`, `entry_id`) — поиск записей по тегу
4. INDEX (`entry_id`) — поиск тегов для записи

**Внешние ключи** (при поддержке БД):
- `entry_id` → `diary.id` ON DELETE CASCADE
- `tag_id` → `diary_tags.id` ON DELETE CASCADE

---

## Этапы реализации

## Stage 1: Database Migration & Entities

**Goal**: Создать таблицы для тегов, обновить Entity и Mapper.

**Success Criteria**: Миграция успешно применяется, новые методы маппера работают.

### Миграция

Файл: `lib/Migration/Version0003Date20260211000000.php`

**Алгоритм**:
1. Создать `diary_tags` с индексами и ограничением UNIQUE
2. Создать `diary_entry_tags` с внешними ключами
3. Тестирование на пустой БД и с существующими записями

**Особенности**:
- Кроссплатформенность: поддержка MySQL, SQLite, PostgreSQL
- Foreign Keys: опциональны (некоторые хосты их отключают)
- Rollback: чистая миграция (данные старых записей не затрагиваются)

### Новые Entity классы

**Файл**: `lib/Db/Tag.php`
```php
class Tag extends Entity {
    protected $uid;          // owner
    protected $tagName;      // unique per user
    protected $createdAt;    // datetime
}
```

**Файл**: `lib/Db/EntryTag.php`
```php
class EntryTag extends Entity {
    protected $entryId;
    protected $tagId;
}
```

### Обновление Entry.php

Добавить поле `tags` (массив объектов тега):
```php
protected $tags; // array of Tag objects (не сохраняется в БД, только при serialization)
```

### Новые Mapper классы

**Файл**: `lib/Db/TagMapper.php` — управление тегами
```php
class TagMapper extends QBMapper {
    // findByUser(uid): Tag[] — все теги пользователя
    // findOrCreate(uid, tagName): Tag — найти или создать тег
    // deleteUnusedTags(uid): void — удалить теги без записей
    // findByName(uid, tagName): Tag — поиск конкретного тега
}
```

**Файл**: `lib/Db/EntryTagMapper.php` — управление связями
```php
class EntryTagMapper extends QBMapper {
    // attach(entryId, tagIds): void — привязать теги к записи
    // detach(entryId): void — удалить все теги записи
    // findTagsByEntry(entryId): Tag[] — теги для записи
    // findEntriesByTag(uid, tagId, limit, offset): Entry[] — записи с тегом
    // countEntriesByTag(uid, tagId): int — количество записей с тегом
}
```

### Обновление EntryMapper.php

Добавить методы для работы с тегами:
```php
// findByDateWithTags(uid, date): Entry[] — с загруженными тегами
// findLastWithTags(uid, amount): Entry[] — последние записи с тегами
// findByTag(uid, tagId, limit, offset): Entry[] — записи по тегу
```

**Tests**:
- Unit-тесты миграции на пустой и заполненной БД
- Unit-тесты Tag и EntryTag entity
- Unit-тесты TagMapper (CRUD)
- Unit-тесты EntryTagMapper (attach/detach)

**Status**: Not Started

---

## Stage 2: Backend API — Tag Endpoints

**Goal**: Новые API endpoints для работы с тегами.

**Success Criteria**: Endpoints работают, возвращают корректные данные, защищены от IDOR.

### Новые API endpoints

| Метод | URL | Описание | Response |
|-------|-----|----------|----------|
| GET | `/api/tags` | Все теги пользователя с кол-вом записей | `[{id, name, count}, ...]` |
| GET | `/api/entries/tag/{tagId}` | Записи с конкретным тегом | `[{id, date, excerpt, tags}, ...]` |
| POST | `/api/entry/{id}/tags` | Обновить теги для записи | `{tags: [{id, name}, ...]}` (deprecated, использовать PUT /entry/{id}) |

### Обновление существующих endpoints

| Метод | URL | Изменение |
|-------|-----|-----------|
| GET | `/api/entry/{id}` | Добавить `tags: [{id, name}, ...]` в ответ |
| PUT | `/api/entry/{id}` | На сохранение автоматически парсить #tags из content |
| GET | `/api/last-entries/{amount}` | Добавить `tags: [{id, name}, ...]` в ответ |
| GET | `/api/entries/{date}` | Добавить `tags` в каждую запись |

### Контроллер — новые методы

**Файл**: `lib/Controller/PageController.php` (добавить методы)

```php
/**
 * GET /api/tags
 * Получить все теги пользователя с кол-вом использований
 */
public function getTags(): DataResponse

/**
 * GET /api/entries/tag/{tagId}
 * Получить записи по тегу с пагинацией
 */
public function getEntriesByTag(int $tagId, int $limit = 50, int $offset = 0): DataResponse

/**
 * PUT /api/entry/{id} — ОБНОВЛЕННЫЙ
 * Парсить #tags из content, создавать/обновлять теги, обновлять связи
 */
public function updateEntryById(int $id, string $content): DataResponse
```

### Логика парсинга тегов на бэкенде

```php
private function extractAndSyncTags(int $entryId, string $content): array {
    // 1. Парсить #tags из контента
    // 2. Для каждого тега: findOrCreate в TagMapper
    // 3. Обновить связи в EntryTagMapper (detach старые, attach новые)
    // 4. Вернуть массив TagObject'ов
    // 5. Удалить неиспользуемые теги (cleanupUnusedTags)
}
```

### Безопасность

- Проверка владельца: перед GET записи по тегу — проверить `uid`
- IDOR защита: нельзя доступить к тегам других пользователей
- SQL injection: использовать параметризованные запросы
- Валидация: length(tagName) ≤ 50, matching regex

**Tests**:
- Integration-тесты для новых endpoints
- Тесты парсинга #tags (edges cases: несколько на одной строке, в начале/конце, специальные символы)
- Тесты фильтрации записей по тегу
- Тесты IDOR защиты
- Тесты удаления неиспользуемых тегов

**Status**: Not Started

---

## Stage 3: Backend Refactoring — Tag Service

**Goal**: Отделить логику тегов в отдельный сервис (для переиспользования).

**Success Criteria**: TagService используется везде, code maintainable, testable.

### Новый класс TagService

**Файл**: `lib/Service/TagService.php`

```php
class TagService {
    private TagMapper $tagMapper;
    private EntryTagMapper $entryTagMapper;

    /**
     * Парсить теги из текста и синхронизировать с БД
     */
    public function syncTagsForEntry(string $uid, int $entryId, string $content): array

    /**
     * Получить облако тегов для пользователя
     */
    public function getTagCloud(string $uid, int $limit = 100): array

    /**
     * Найти записи по тегу
     */
    public function findEntriesByTag(string $uid, int $tagId, int $limit, int $offset): array

    /**
     * Удалить теги из записи
     */
    public function removeTagsFromEntry(int $entryId): void
}
```

### Обновление PageController

Внедрить TagService, использовать вместо прямых вызовов mapper'ов:

```php
public function updateEntryById(int $id, string $content): DataResponse {
    $entry = $this->mapper->findById($id);
    // ...
    $tags = $this->tagService->syncTagsForEntry($this->userId, $id, $content);
    // ...
}
```

**Tests**:
- Unit-тесты TagService для всех методов
- Integration-тесты с реальной БД

**Status**: Not Started

---

## Stage 4: Frontend — Tag Cloud Component & Sidebar

**Goal**: Показать облако тегов в правой боковой панели, фильтрация по тегам.

**Success Criteria**: Облако тегов видно, клик на тег фильтрует записи, нет console errors.

### Новые Vue компоненты

#### TagCloud.vue

**Назначение**: Визуализация облака тегов в правой панели

**Props**:
- `tags` (Array): массив объектов `{id, name, count}`
- `activeTag` (Number): текущий выбранный тег (null если фильтра нет)

**Events**:
- `select-tag(tagId)` — клик на тег в облаке

**Функциональность**:
- Показывает облако с размером шрифта в зависимости от `count` (частоты)
- Подсветка выбранного тега
- Hover эффект
- Пустое состояние если нет тегов

**Пример рендера**:
```html
<div class="tag-cloud">
  <span class="tag" :class="{ active: activeTag === tag.id }"
        @click="$emit('select-tag', tag.id)"
        :style="{ fontSize: calculateFontSize(tag.count) }">
    #{{ tag.name }}
    <small>({{ tag.count }})</small>
  </span>
</div>
```

#### TagFilter.vue

**Назначение**: Показать активный фильтр и кнопку сброса

**Props**:
- `activeTag` (Object): `{id, name}` или null

**Events**:
- `clear-filter()` — сброс фильтра

**Функциональность**:
- Показывает "Фильтр: #work (15 записей)"
- Кнопка ✕ для сброса
- Пусто если нет фильтра

### Обновление Diary.vue

**Структура**:
```vue
<template>
  <NcContent>
    <NcAppNavigation><!-- LEFT sidebar: календарь и меню --></NcAppNavigation>
    <NcAppContent>
      <!-- MAIN content: router-view -->
    </NcAppContent>
    <NcAppSidebar v-if="showRightSidebar"><!-- NEW RIGHT sidebar -->
      <TagFilter :activeTag="activeTag" @clear-filter="clearTagFilter" />
      <TagCloud :tags="tags" :activeTag="activeTagId" @select-tag="selectTag" />
    </NcAppSidebar>
  </NcContent>
</template>

<script>
data() {
  return {
    tags: [],
    activeTagId: null,
    showRightSidebar: true,
  }
}

methods: {
  fetchTags() {
    axios.get(generateUrl('apps/nextdiary/api/tags'))
      .then(response => {
        this.tags = response.data || []
      })
  },

  selectTag(tagId) {
    this.activeTagId = tagId
    this.$router.push({
      name: 'tag-entries',
      params: { tagId: String(tagId) }
    })
  },

  clearTagFilter() {
    this.activeTagId = null
    this.$router.push({ name: 'day', params: { date: this.currentDate } })
  }
}
</script>
```

### Обновление router.js

```js
{
  path: 'tag/:tagId',
  name: 'tag-entries',
  component: TagEntriesView,
  props: true,
}
```

### Новый компонент TagEntriesView.vue

**Назначение**: Показать список записей с выбранным тегом

**Props**:
- `tagId` (String)

**Функциональность**:
- Загружает записи по тегу через GET `/api/entries/tag/{tagId}`
- Показывает список карточек (как DayView)
- Фильтр: "Показаны записи с тегом #work"
- Кнопка для возврата к календарю

**Estructura**:
```vue
<template>
  <div class="tag-entries-view">
    <div class="tag-header">
      <NcButton type="tertiary" @click="goBack">
        <ArrowLeft :size="20" />
      </NcButton>
      <h2>Записи с тегом #{{ tagName }}</h2>
    </div>
    <div v-if="entries.length > 0" class="entries-list">
      <div v-for="entry in entries" :key="entry.id" class="entry-card" @click="openEntry(entry)">
        <div class="entry-info">
          <span class="entry-date">{{ formatDate(entry.date) }}</span>
          <span class="entry-preview">{{ entry.excerpt }}</span>
        </div>
      </div>
    </div>
    <NcEmptyContent v-else :name="t('nextdiary', 'No entries with this tag')" />
  </div>
</template>
```

### Обновление DayView.vue

Показывать теги для каждой записи:
```vue
<div class="entry-card">
  <div class="entry-card-header">
    <span class="entry-time">{{ formatTime(entry.createdAt) }}</span>
    <div v-if="entry.tags && entry.tags.length" class="entry-tags">
      <span v-for="tag in entry.tags" :key="tag.id" class="tag-badge">#{{ tag.name }}</span>
    </div>
  </div>
</div>
```

### Обновление Editor.vue

После загрузки записи — извлечь теги из контента и показать:
```vue
<div v-if="tags && tags.length" class="editor-tags">
  <strong>Теги:</strong>
  <span v-for="tag in tags" :key="tag.id" class="tag-badge">#{{ tag.name }}</span>
</div>
```

### Стили (SCSS)

```scss
.tag-cloud {
  padding: 12px;

  .tag {
    display: inline-block;
    margin: 4px;
    padding: 6px 10px;
    background-color: var(--color-background-hover);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.2s;

    &:hover {
      background-color: var(--color-primary);
      color: white;
    }

    &.active {
      background-color: var(--color-primary);
      color: white;
      font-weight: 700;
    }

    small {
      font-size: 0.8em;
      opacity: 0.8;
      margin-left: 4px;
    }
  }
}

.tag-badge {
  display: inline-block;
  padding: 4px 8px;
  background-color: var(--color-primary);
  color: white;
  border-radius: 4px;
  font-size: 0.85em;
  margin-right: 4px;

  &:hover {
    opacity: 0.8;
  }
}
```

**Tests**:
- Component-тесты TagCloud (render, click, active state)
- Component-тесты TagFilter (show/hide, clear)
- Component-тесты TagEntriesView (load entries, empty state)
- Integration-тесты: DayView + теги, Editor + теги
- E2E-тесты: полный цикл — создать запись, добавить #tag, видеть в облаке, кликнуть, отфильтровать

**Status**: Not Started

---

## Stage 5: Frontend — Inline Tag Search & Display

**Goal**: Поиск по названию тега, улучшение UX облака.

**Success Criteria**: Поиск работает, интеллектуальная фильтрация облака.

### Новый компонент TagSearch.vue

**Назначение**: Поле поиска по названию тега

**Props**:
- `tags` (Array): все теги
- `query` (String): текущее значение поиска

**Events**:
- `update:query(value)` — обновление текста поиска

**Функциональность**:
```vue
<template>
  <div class="tag-search">
    <NcTextField
      :value="query"
      @input="$emit('update:query', $event.target.value)"
      placeholder="Поиск тегов..."
    />
  </div>
</template>
```

### Обновление Diary.vue

```js
data() {
  return {
    tags: [],
    filteredTags: [],
    tagSearchQuery: '',
  }
}

computed: {
  filteredTags() {
    if (!this.tagSearchQuery) return this.tags
    return this.tags.filter(tag =>
      tag.name.includes(this.tagSearchQuery.toLowerCase())
    )
  }
}
```

### Сортировка облака тегов

**Варианты**:
1. По частоте (count DESC) — популярные теги выше
2. По алфавиту (name ASC)
3. По дате создания (created_at DESC)
4. По релевантности для текущего дня

**Рекомендация**: По умолчанию по частоте, с кнопкой для изменения.

**Tests**:
- Component-тесты TagSearch (input, filter, clear)
- Tests для различных вариантов сортировки

**Status**: Not Started

---

## Stage 6: Export Service — Update PDF/Markdown

**Goal**: Обновить экспорт для показа тегов в PDF и Markdown.

**Success Criteria**: Экспорт включает теги в корректном формате.

### Изменения в ConversionService

**Логика**:
- PDF: показать теги после заголовка записи `Tags: #work, #health`
- Markdown: формат `[tags]: #work #health` как YAML metadata

**Пример PDF вывода**:
```
═══════════════════════════════════════════
10 февраля 2026 г.

Tags: #work #health #travel

Содержимое записи...
═══════════════════════════════════════════
```

**Пример Markdown вывода**:
```markdown
---
tags: [work, health, travel]
date: 2026-02-10
---

## 10 февраля 2026

Содержимое записи...
```

**Tests**:
- E2E-тесты экспорта с тегами
- Проверка корректности форматирования

**Status**: Not Started

---

## Stage 7: Versioning & Final Cleanup

**Goal**: Обновить версию, удалить deprecated код, финальное тестирование.

**Success Criteria**: Все версии обновлены, нет console errors, готово к продакшену.

### Обновление версий

**appinfo/info.xml**:
```xml
<version>0.0.3</version>
```

**package.json**:
```json
"version": "0.0.3"
```

### Cleanup

- Удалить deprecated старые endpoints (если есть)
- Обновить README.md с информацией о тегах
- Обновить CLAUDE.md с новыми workflows
- Проверить отсутствие console errors/warnings
- Финальное тестирование на всех браузерах и размерах экрана

**Tests**:
- Full E2E тест: создать запись с #tags → видеть в облаке → кликнуть → отфильтровать → экспортировать
- Cross-browser testing
- Mobile responsiveness testing

**Status**: Not Started

---

## Диаграмма архитектуры

```
┌──────────────────────────────────────────────────────────────────────┐
│                         Frontend (Vue 2)                              │
│                                                                        │
│  ┌─────────────────┐  ┌──────────────┐  ┌────────────────────────┐  │
│  │ NcAppNavigation │  │ NcAppContent │  │ NcAppSidebar [NEW]     │  │
│  │   (LEFT)        │  │  (MAIN)      │  │   (RIGHT)              │  │
│  │                 │  │              │  │                        │  │
│  │ • Календарь     │  │ • DayView    │  │ • TagFilter.vue        │  │
│  │ • Меню записей  │  │ • Editor     │  │ • TagCloud.vue         │  │
│  │ • Экспорт       │  │ • TagEntries │  │ • TagSearch.vue        │  │
│  └────────┬────────┘  └──────┬───────┘  └────────┬───────────────┘  │
│           │                  │                    │                   │
│  ┌────────┴──────────────────┴────────────────────┴───────────────┐  │
│  │                      Vue Router                                 │  │
│  │  /day/:date → DayView                                          │  │
│  │  /entry/:id → Editor                                           │  │
│  │  /tag/:tagId → TagEntriesView [NEW]                           │  │
│  └────────────────────────┬─────────────────────────────────────┘  │
└─────────────────────────────┼──────────────────────────────────────┘
                              │ Axios HTTP
┌─────────────────────────────┼──────────────────────────────────────┐
│                       Backend (PHP)                                  │
│                                                                      │
│  ┌──────────────────────────┴─────────────────────────────────┐   │
│  │              PageController                                 │   │
│  │                                                             │   │
│  │  GET  /api/tags                  → getTags [NEW]          │   │
│  │  GET  /api/entries/tag/{id}      → getEntriesByTag [NEW]  │   │
│  │  PUT  /api/entry/{id}            → updateEntryById (парс) │   │
│  │  GET  /api/entry/{id}            → + tags в ответе       │   │
│  │  GET  /api/entries/{date}        → + tags в каждой запис │   │
│  │  ... (остальные endpoints)                                 │   │
│  └──────────────────────────┬─────────────────────────────────┘   │
│                             │                                       │
│  ┌──────────────────────────┴─────────────────────────────────┐   │
│  │            TagService [NEW]                                │   │
│  │                                                             │   │
│  │  syncTagsForEntry(uid, id, content)                        │   │
│  │  getTagCloud(uid, limit)                                   │   │
│  │  findEntriesByTag(uid, tagId, limit, offset)              │   │
│  │  removeTagsFromEntry(id)                                   │   │
│  └──────────────────────────┬─────────────────────────────────┘   │
│                             │                                       │
│  ┌──────────────────────────┴─────────────────────────────────┐   │
│  │            Mapper Classes                                   │   │
│  │                                                             │   │
│  │  • EntryMapper (существующий)                              │   │
│  │  • TagMapper [NEW]                                          │   │
│  │  • EntryTagMapper [NEW]                                     │   │
│  └──────────────────────────┬─────────────────────────────────┘   │
│                             │                                       │
└─────────────────────────────┼───────────────────────────────────────┘
                              │
┌─────────────────────────────┼───────────────────────────────────────┐
│                         Database                                     │
│                                                                      │
│  ┌──────────────────────────┴──────────────────────────────────┐   │
│  │  TABLE diary (существующая)                                │   │
│  │  ─────────────────────────────────────────────────────────  │   │
│  │  id, uid, entry_date, entry_content, created_at, updated   │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  TABLE diary_tags [NEW]                                    │   │
│  │  ──────────────────────────────────────────────────────     │   │
│  │  id, uid, tag_name (UNIQUE per uid), created_at            │   │
│  │                                                              │   │
│  │  UNIQUE(uid, tag_name)                                      │   │
│  │  INDEX(uid, created_at DESC)                                │   │
│  └──────────────────┬─────────────────────────────────────────┘   │
│                     │                                                │
│  ┌──────────────────┴─────────────────────────────────────────┐   │
│  │  TABLE diary_entry_tags [NEW]                              │   │
│  │  ──────────────────────────────────────────────────────     │   │
│  │  id, entry_id, tag_id                                       │   │
│  │                                                              │   │
│  │  UNIQUE(entry_id, tag_id)                                   │   │
│  │  INDEX(tag_id, entry_id)                                    │   │
│  │  FK: entry_id → diary.id ON DELETE CASCADE                  │   │
│  │  FK: tag_id → diary_tags.id ON DELETE CASCADE               │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

---

## API Specification (OpenAPI)

### GET /api/tags

**Description**: Получить все теги пользователя с кол-вом использований

**Response** (200):
```json
[
  {
    "id": 1,
    "name": "work",
    "count": 15,
    "createdAt": "2026-02-01T10:00:00Z"
  },
  {
    "id": 2,
    "name": "health",
    "count": 8,
    "createdAt": "2026-02-05T14:30:00Z"
  }
]
```

### GET /api/entries/tag/{tagId}?limit=50&offset=0

**Description**: Получить записи с конкретным тегом

**Parameters**:
- `tagId` (integer): ID тега
- `limit` (integer, default=50): кол-во записей
- `offset` (integer, default=0): смещение для пагинации

**Response** (200):
```json
[
  {
    "id": 10,
    "entryDate": "2026-02-10",
    "entryContent": "Отличный день в офисе #work #health",
    "createdAt": "2026-02-10T14:30:00Z",
    "excerpt": "Отличный день в офисе...",
    "tags": [
      { "id": 1, "name": "work" },
      { "id": 2, "name": "health" }
    ]
  }
]
```

### GET /api/entry/{id} (обновленный)

**Response** (200):
```json
{
  "id": 10,
  "entryDate": "2026-02-10",
  "entryContent": "Содержимое #work #travel",
  "createdAt": "2026-02-10T14:30:00Z",
  "updatedAt": "2026-02-11T10:00:00Z",
  "tags": [
    { "id": 1, "name": "work" },
    { "id": 3, "name": "travel" }
  ]
}
```

### PUT /api/entry/{id} (обновленный)

**Request**:
```json
{
  "content": "Обновленное содержимое #work #health #gym"
}
```

**Response** (200):
```json
{
  "id": 10,
  "entryDate": "2026-02-10",
  "entryContent": "Обновленное содержимое #work #health #gym",
  "createdAt": "2026-02-10T14:30:00Z",
  "updatedAt": "2026-02-11T10:15:00Z",
  "tags": [
    { "id": 1, "name": "work" },
    { "id": 2, "name": "health" },
    { "id": 4, "name": "gym" }
  ]
}
```

---

## Тестовые сценарии

### Unit Tests

**TagMapper**:
- `findByUser()` — получить все теги пользователя
- `findOrCreate()` — создать тег если не существует
- `deleteUnusedTags()` — удалить теги без привязок

**EntryTagMapper**:
- `attach()` — привязать теги к записи
- `detach()` — удалить теги с записи
- `findTagsByEntry()` — получить теги записи
- `findEntriesByTag()` — получить записи по тегу

**TagService**:
- `syncTagsForEntry()` — парсить и синхронизировать теги
- `getTagCloud()` — облако тегов с фильтром и сортировкой

### Integration Tests

- `PUT /api/entry/{id}` с новыми тегами → теги созданы/обновлены в БД
- `GET /api/tags` → возвращает все теги пользователя с count
- `GET /api/entries/tag/{tagId}` → возвращает только записи с этим тегом
- Удаление записи → теги остаются (если используются другими записями)
- Удаление последней записи с тегом → тег удаляется
- IDOR protection: user A не может видеть теги user B

### Component Tests (Vue)

- `TagCloud.vue`: рендер, click, hover, active state
- `TagFilter.vue`: show/hide, clear
- `TagSearch.vue`: input, filter, debounce
- `TagEntriesView.vue`: load entries, empty state, pagination
- `DayView.vue`: show tags in entry cards
- `Editor.vue`: show extracted tags, auto-update on content change

### E2E Tests

1. **Создание записи с тегами**:
   - Открыть DayView
   - Создать новую запись
   - Написать "Отличный день #work #health"
   - Сохраниться (автосохранение)
   - Проверить что теги видны в облаке справа

2. **Фильтрация по тегу**:
   - Кликнуть на #work в облаке
   - Перейти на страницу TagEntriesView
   - Проверить что показаны только записи с #work

3. **Поиск по названию тега**:
   - Ввести "wor" в поле поиска тегов
   - Облако отфильтровано (показаны только теги с "wor")

4. **Экспорт с тегами**:
   - Экспортировать в PDF
   - Проверить что теги видны в PDF
   - Экспортировать в Markdown
   - Проверить YAML metadata с тегами

---

## Риск-ассессмент

| Риск | Вероятность | Влияние | Mitigation |
|------|------------|--------|------------|
| Ошибка парсинга #tags (regex) | Средняя | Низкое | Комплексное unit тестирование regex |
| N+1 queries при загрузке записей с тегами | Средняя | Среднее | Оптимизировать запросы (JOIN или batch load) |
| Производительность облака при 1000+ тегов | Низкая | Среднее | Пагинация, limit в запросе, кэширование |
| Миграция БД неудача | Низкая | Критическое | Backup перед миграцией, тесты на копии |
| Конфликт маршрутов router `/tag/:tagId` | Очень низкая | Среднее | Четкие названия routes, тестирование |
| UTF-8 в тегах (кириллица) | Низкая | Низкое | Использовать `mb_*` функции, юникод в regex |

---

## Чек-лист Definition of Done

### Database & Backend

- [ ] Миграция создает таблицы `diary_tags` и `diary_entry_tags` без ошибок
- [ ] TagMapper и EntryTagMapper работают (CRUD)
- [ ] TagService корректно парсит #tags из контента
- [ ] API endpoint GET /api/tags возвращает облако с count
- [ ] API endpoint GET /api/entries/tag/{id} возвращает отфильтрованные записи
- [ ] PUT /api/entry/{id} синхронизирует теги
- [ ] IDOR защита работает (user не может видеть чужие теги)
- [ ] Все unit/integration тесты проходят

### Frontend Components

- [ ] TagCloud.vue рендерится без ошибок
- [ ] TagFilter.vue показывает/скрывает активный фильтр
- [ ] TagSearch.vue фильтрует облако по названию
- [ ] TagEntriesView.vue загружает и показывает записи по тегу
- [ ] DayView показывает теги в карточках записей
- [ ] Editor показывает извлеченные теги из контента
- [ ] Клик на тег в облаке → переход на TagEntriesView

### Integration & E2E

- [ ] Полный цикл: создать запись → добавить #tag → видеть в облаке → кликнуть → отфильтровать
- [ ] Экспорт PDF/Markdown включает теги
- [ ] Нет console errors/warnings
- [ ] Работает на Chrome, Firefox, Safari
- [ ] Mobile responsive (< 768px)

### Documentation & Version

- [ ] Версия обновлена на 0.0.3 (info.xml, package.json)
- [ ] README.md обновлен с информацией о тегах
- [ ] CLAUDE.md обновлен с новыми workflows
- [ ] Все новые методы имеют PHPDoc комментарии
- [ ] Нет deprecated кода или он явно marked as @deprecated

### Quality

- [ ] Code прошел linting (ESLint, PHPStan)
- [ ] Тесты имеют > 70% coverage для новых классов
- [ ] No SQL injection vulnerabilities
- [ ] No IDOR/authorization bypasses
- [ ] Performance: GET /api/tags < 200ms, GET /api/entries/tag/{id} < 500ms

---

## Временная оценка (примерно)

| Этап | Часы | Notes |
|------|------|-------|
| Stage 1: Database & Entities | 4-6h | Миграция, mapper'ы |
| Stage 2: API Endpoints | 6-8h | TagService, парсинг, тесты |
| Stage 3: Service Refactoring | 3-4h | Выделение логики в TagService |
| Stage 4: Frontend Components | 8-12h | 4 новых компонента + обновление 3 существующих |
| Stage 5: Tag Search | 3-4h | Поиск, сортировка |
| Stage 6: Export Update | 3-4h | PDF/Markdown с тегами |
| Stage 7: Versioning & Cleanup | 2-3h | Финальное тестирование |
| **Итого** | **29-41h** | ~1 неделя (при полной занятости) |

---

## Важные замечания

### UTF-8 и специальные символы

В тегах должны быть поддержаны символы из разных языков:
```php
// Правильно: поддерживает кириллицу
preg_match_all('/#([\p{L}\p{N}_-]+)/u', $content, $matches);

// Неправильно: только ASCII
preg_match_all('/#([a-zA-Z0-9_-]+)/', $content, $matches);
```

### MySQL strict mode

При миграции убедиться что:
- `STRICT_TRANS_TABLES` не вызывает ошибки с пустыми значениями datetime
- `created_at` и `updated_at` имеют `notnull: false, default: null`

### Кэширование облака тегов

Для оптимизации можно добавить кэширование (Redis):
- Ключ: `user:{uid}:tags:cloud`
- TTL: 1 час (или инвалидировать при изменении тегов)

Но это опциональная оптимизация для Stage 8 (будущая версия).

### Миграция с предыдущей версии

Если пользователь уже использовал v0.0.2:
- Миграция создаст пустые таблицы тегов
- Теги не будут автоматически созданы из старых записей
- Пользователь может вручную отредактировать запись чтобы добавить #tags
- Таким образом теги будут синхронизированы при первом сохранении

Можно добавить convenience method для массовой синхронизации старых записей, но это не обязательно.
