# NextDiary

A personal diary and health journal for Nextcloud.

## Features

- **Markdown editor** - write diary entries with full Markdown support
- **Multiple entries per day** - create several entries for the same date
- **Mood & wellbeing tracking** - rate your mood and wellbeing on a 1-5 scale
- **Tags** — organize entries with tags, auto-extracted from #hashtags
- **Symptoms** — track health symptoms across entries
- **Medications** — log medications linked to diary entries
- **File attachments** — attach files to entries, stored in Nextcloud
- **Calendar navigation** — jump to any date, highlighted days with entries
- **Configurable sidebar** — show/hide sections, reorder tags/symptoms/medications
- **Export** — download entries as Markdown or PDF (single entry, day, date range, or all)
- **Localization** — Russian and English translations included

## Requirements

- Nextcloud 25–30
- PHP 8.0–8.3

## Installation

### From source

1. Clone the repository into your Nextcloud apps directory:
   ```bash
   cd /path/to/nextcloud/apps
   git clone https://github.com/89simpson/nextdiary.git
   ```
2. Enable the app in Nextcloud admin panel under "Apps"

### Building from source

1. Install PHP 8 with `xml` and `mbstring` extensions
2. Install Node.js via [nvm](https://github.com/nvm-sh/nvm)
3. Install dependencies and build:
   ```bash
   make
   ```

## License

This project is licensed under the [GNU Affero General Public License v3.0](LICENSE).

## Credits

Originally inspired by the [Diary](https://github.com/danielroehrig/diary) app by Daniel Rohrig. The codebase has been substantially rewritten with new features and architecture.

