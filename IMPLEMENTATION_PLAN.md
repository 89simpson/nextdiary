# IMPLEMENTATION PLAN: Multiple Entries Per Day (v0.0.2)

## Overview

Переход от модели "одна запись на день" к "несколько записей в день" с сохранением обратной совместимости.

**Текущая модель:** `id = userId + date` (composite string key) -> 1 запись/день
**Новая модель:** `id = auto-increment integer` -> N записей/день с timestamps

---

## Stage 1: Database Migration

**Goal**: Миграция схемы БД на auto-increment ID с timestamps, сохранение всех существующих данных.

**Success Criteria**: Все старые записи мигрированы, новая схема поддерживает несколько записей на дату.

### Новая схема таблицы `diary`

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | INTEGER AUTO_INCREMENT | PRIMARY KEY |
| `uid` | VARCHAR(64) NOT NULL | userId |
| `entry_date` | VARCHAR(10) NOT NULL | YYYY-MM-DD |
| `entry_content` | TEXT NULL | Markdown content |
| `created_at` | DATETIME NOT NULL | Время создания |
| `updated_at` | DATETIME NOT NULL | Время последнего изменения |

### Индексы

1. **PRIMARY KEY** (`id`) - автоматически
2. **INDEX** `idx_uid_date` (`uid`, `entry_date`) - поиск записей за дату, подсветка календаря
3. **INDEX** `idx_uid_created` (`uid`, `created_at` DESC) - последние записи для бокового меню

### Миграция данных

Файл: `lib/Migration/Version0002Date20260210000000.php`

**Алгоритм:**
1. Создать `diary_new` с новой схемой и индексами
2. Скопировать данные: `INSERT INTO diary_new (uid, entry_date, entry_content, created_at, updated_at) SELECT uid, entry_date, entry_content, CONCAT(entry_date, ' 12:00:00'), CONCAT(entry_date, ' 12:00:00') FROM diary ORDER BY uid, entry_date`
3. `DROP TABLE diary`
4. `ALTER TABLE diary_new RENAME TO diary`
5. Верификация: `COUNT(*)` совпадает

**Кроссплатформенность (SQLite/MySQL/PostgreSQL):**
- AUTO_INCREMENT: Nextcloud DBAL `'autoincrement' => true`
- `updated_at`: обновлять в PHP коде (SQLite не поддерживает `ON UPDATE CURRENT_TIMESTAMP`)

**Rollback:** Если миграция не завершена — `diary` не затронута. Если завершена — обратная миграция Version0003.

**Tests**: Unit-тест миграции на пустой БД + с тестовыми данными.

**Status**: Not Started

---

## Stage 2: Backend Entity & Mapper

**Goal**: Обновить Entry entity и EntryMapper для работы с новой схемой.

**Success Criteria**: CRUD операции работают с integer ID и timestamps.

### Entry.php — изменения

- `id`: тип `integer` (было `string`)
- Новые поля: `createdAt` (datetime), `updatedAt` (datetime)
- `addType('id', 'integer')`, `addType('createdAt', 'datetime')`, `addType('updatedAt', 'datetime')`
- Обновить `jsonSerialize()`: добавить `createdAt`, `updatedAt`

### EntryMapper.php — изменения

**Новые методы:**
- `findByDate(uid, date): array` — все записи за дату, ORDER BY created_at ASC
- `findById(id): Entry` — одна запись по integer ID

**Модифицированные методы:**
- `findLast(uid, amount)`: ORDER BY `created_at DESC` (было `entry_date DESC`)
- `findAllDates(uid)`: добавить `DISTINCT` (теперь могут быть дубликаты дат)

**Удалённые/deprecated:**
- `find(uid, date)` — заменяется на `findByDate()` (возвращает массив, не один объект)

**Tests**: Unit-тесты для каждого метода mapper.

**Status**: Not Started

---

## Stage 3: Backend API (Controller & Routes)

**Goal**: Новые API endpoints с обратной совместимостью.

**Success Criteria**: Новые endpoints работают, старые продолжают функционировать.

### Новые endpoints

| Метод | URL | Описание | Response |
|-------|-----|----------|----------|
| GET | `/entries/date/{date}` | Все записи за дату | `[{id, entryDate, entryContent, createdAt, updatedAt}, ...]` |
| GET | `/entry/{id}` | Запись по ID | `{id, entryDate, entryContent, createdAt, updatedAt}` |
| POST | `/entry` | Создать запись | `{id, ...}` (201) |
| PUT | `/entry/{id}` | Обновить запись | `{id, ...}` (200) |
| DELETE | `/entry/{id}` | Удалить запись | 204 No Content |
| GET | `/last-entries/{amount}` | Последние N записей | `[{id, date, createdAt, excerpt}, ...]` |

### Сохраняемые endpoints (обратная совместимость)

| Метод | URL | Поведение |
|-------|-----|-----------|
| GET | `/entry/{date}` (string) | Первая запись за дату или `{isEmpty: true}` |
| PUT | `/entry/{date}` (string) | Upsert первой записи (legacy) |
| GET | `/entries/{amount}` (int) | Последние N (сохранить как есть) |
| GET | `/entry-dates` | Уникальные даты (без изменений) |

**Разрешение конфликтов маршрутов:**
- `/entry/{date}` (GET, string) vs `/entry/{id}` (GET, int) — различать по формату параметра: если содержит `-` (YYYY-MM-DD) → date, иначе → id
- Или переименовать: `/entry/by-date/{date}` и `/entry/{id}`

### Безопасность

- Проверка владельца: `entry->getUid() === $this->userId` для GET/PUT/DELETE по ID
- CSRF: POST/PUT/DELETE требуют токен (по умолчанию в Nextcloud)
- Валидация: `strip_tags()` + `sanitizeUtf8()` при записи
- Защита от IDOR: 403 при попытке доступа к чужим записям

**Tests**: Integration-тесты API.

**Status**: Not Started

---

## Stage 4: Frontend — Router & Components

**Goal**: Обновить Vue frontend для поддержки нескольких записей в день.

**Success Criteria**: Пользователь может создавать, просматривать, редактировать и удалять несколько записей в один день.

### Router.js — новые маршруты

```
/                       → redirect на /day/{сегодня}
/day/:date              → DayView (список записей дня)
/entry/:id              → Editor (редактирование записи)
/date/:date             → redirect на /day/:date (обратная совместимость)
```

### Diary.vue — изменения

- Сохранить как контейнер с навигацией и боковым меню
- Боковое меню: записи (не дни) с форматом "10 фев 14:30 — Превью..."
- `<NcAppContent>` содержит `<router-view />` (DayView или Editor)
- `fetchLastEntries()` получает `[{id, date, createdAt, excerpt}, ...]`
- Клик по записи в боковом меню → `/entry/{id}`

### Новый компонент: DayView.vue

- Показывает все записи выбранного дня
- Кнопка "Новая запись" → POST /entry → redirect на /entry/{id}
- Список карточек записей (время + превью + кнопка удаления)
- Пустое состояние если записей нет

### Editor.vue — изменения

- Prop: `id` (integer) вместо `date` (string)
- API: GET/PUT `/entry/{id}` вместо `/entry/{date}`
- Заголовок: "10 февраля 2026 в 14:30" (дата + время)
- Кнопка "Назад к дню" → `/day/{entry.date}`
- Автосохранение: PUT по ID (debounce 500ms, как сейчас)

### UX Flow

1. Календарь → `/day/2026-02-10` → список записей дня
2. "Новая запись" → POST создаёт запись → `/entry/125` → редактор
3. Клик на карточку → `/entry/123` → редактор
4. Боковое меню → `/entry/{id}` → редактор
5. Кнопки ←→ переключают дни → `/day/{date}`

**Tests**: Component tests для DayView, обновлённых Diary и Editor.

**Status**: Not Started

---

## Stage 5: Export Service & Cleanup

**Goal**: Обновить экспорт (PDF/Markdown) для работы с несколькими записями, финальная очистка.

**Success Criteria**: Экспорт корректно обрабатывает несколько записей в день.

### ConversionService

- Экспорт за дату: объединить все записи дня с разделителем (время + контент)
- Экспорт всех: группировка по дням, внутри дня — по `created_at`

### Версионирование

- `appinfo/info.xml`: version → `0.0.2`
- `package.json`: version → `0.0.2`

### Cleanup

- Удалить deprecated endpoints (после стабилизации)
- Обновить README.md

**Tests**: E2E тест полного цикла: создание нескольких записей → экспорт.

**Status**: Not Started

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────┐
│                    Frontend (Vue 2)                   │
│                                                       │
│  ┌──────────┐   ┌──────────┐   ┌─────────────────┐  │
│  │ Diary.vue│   │DayView   │   │ Editor.vue      │  │
│  │(container│   │.vue      │   │ (entry by ID)   │  │
│  │ sidebar) │   │(entries  │   │                 │  │
│  │          │   │ for day) │   │                 │  │
│  └──────────┘   └──────────┘   └─────────────────┘  │
│       │              │                │               │
│  ┌────┴──────────────┴────────────────┴───────────┐  │
│  │              Vue Router                         │  │
│  │  /day/:date → DayView                          │  │
│  │  /entry/:id → Editor                           │  │
│  └─────────────────────┬──────────────────────────┘  │
└────────────────────────┼─────────────────────────────┘
                         │ Axios HTTP
┌────────────────────────┼─────────────────────────────┐
│                    Backend (PHP)                       │
│                                                       │
│  ┌─────────────────────┴──────────────────────────┐  │
│  │            PageController                       │  │
│  │                                                 │  │
│  │  GET  /entries/date/{date}  → getEntriesByDate  │  │
│  │  GET  /entry/{id}           → getEntryById      │  │
│  │  POST /entry                → createEntry       │  │
│  │  PUT  /entry/{id}           → updateEntryById   │  │
│  │  DELETE /entry/{id}         → deleteEntry       │  │
│  │  GET  /last-entries/{n}     → getLastEntries    │  │
│  │  GET  /entry-dates          → getEntryDates     │  │
│  └─────────────────────┬──────────────────────────┘  │
│                        │                              │
│  ┌─────────────────────┴──────────────────────────┐  │
│  │           EntryMapper (QBMapper)                │  │
│  │                                                 │  │
│  │  findByDate(uid, date)  → Entry[]               │  │
│  │  findById(id)           → Entry                 │  │
│  │  findLast(uid, n)       → Entry[]               │  │
│  │  findAllDates(uid)      → string[]              │  │
│  │  insert(Entry)          → Entry                 │  │
│  │  update(Entry)          → Entry                 │  │
│  │  delete(Entry)          → void                  │  │
│  └─────────────────────┬──────────────────────────┘  │
└────────────────────────┼─────────────────────────────┘
                         │
┌────────────────────────┼─────────────────────────────┐
│                    Database                           │
│                                                       │
│  ┌─────────────────────┴──────────────────────────┐  │
│  │  TABLE diary                                    │  │
│  │  ─────────────────────────────────────────────  │  │
│  │  id            INTEGER AUTO_INCREMENT PK        │  │
│  │  uid           VARCHAR(64) NOT NULL             │  │
│  │  entry_date    VARCHAR(10) NOT NULL             │  │
│  │  entry_content TEXT NULL                        │  │
│  │  created_at    DATETIME NOT NULL                │  │
│  │  updated_at    DATETIME NOT NULL                │  │
│  │                                                 │  │
│  │  INDEX idx_uid_date (uid, entry_date)           │  │
│  │  INDEX idx_uid_created (uid, created_at DESC)   │  │
│  └─────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────┘
```

---

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|------------|--------|------------|
| Ошибка миграции БД | Средняя | Критический | Backup перед миграцией, тест на копии |
| Конфликт маршрутов `/entry/{date}` vs `/entry/{id}` | Высокая | Средний | Использовать `/entries/date/{date}` для нового API |
| Пустые записи при создании | Средняя | Низкий | Удалять пустые записи при уходе со страницы |
| Производительность с большим количеством записей | Низкая | Средний | Индексы + пагинация |

---

## Definition of Done

- [ ] Миграция БД работает на SQLite, MySQL, PostgreSQL
- [ ] Все старые записи мигрированы с корректными timestamps
- [ ] Новые API endpoints работают и защищены (IDOR, CSRF)
- [ ] Старые endpoints сохраняют обратную совместимость
- [ ] Frontend показывает несколько записей в день
- [ ] Боковое меню показывает записи с датой и временем
- [ ] Создание/редактирование/удаление записей работает
- [ ] Календарь подсвечивает даты с записями
- [ ] Экспорт обрабатывает несколько записей в день
- [ ] Нет console errors/warnings
- [ ] Version bumped to 0.0.2
