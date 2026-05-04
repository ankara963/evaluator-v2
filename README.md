# Evaluator V2

Evaluator V2 is a Laravel app for managing course subjects, assigning them to semesters, setting prerequisites, and checking whether a student can proceed based on semester grades.

## First Time Setup

### Prerequisites

- PHP 8.3 or newer
- Composer
- Node.js and npm
- MySQL
- Laravel Herd for local serving on macOS

Run all commands from the Laravel project root. If your terminal is one directory above the app, enter it first:

```bash
cd evaluator-v2
```

### 1. Install PHP Dependencies

```bash
composer install
```

### 2. Create the Environment File

```bash
cp .env.example .env
php artisan key:generate
```

Update these values in `.env` for local development:

```env
APP_NAME="Evaluator V2"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://evaluator-v2.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=evaluator_v2
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Create the Database

Create the local MySQL database if it does not exist yet:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS evaluator_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

If your MySQL user has a password, add `-p` and enter the password when prompted.

### 4. Configure OpenAI Narration

The evaluator works without AI. AI narration is optional.

To keep AI disabled:

```env
GRADE_EVALUATOR_AI_ENABLED=false
```

To enable OpenAI narration:

```env
OPENAI_API_KEY=your_openai_api_key
OPENAI_API_URL=https://api.openai.com/v1/responses
OPENAI_MODEL=gpt-4.1-mini

GRADE_EVALUATOR_AI_ENABLED=true
GRADE_EVALUATOR_AI_API_KEY="${OPENAI_API_KEY}"
GRADE_EVALUATOR_AI_API_URL="${OPENAI_API_URL}"
GRADE_EVALUATOR_AI_MODEL="${OPENAI_MODEL}"
GRADE_EVALUATOR_AI_TIMEOUT=15
```

After changing `.env`, clear cached config:

```bash
php artisan optimize:clear
```

### 5. Run Migrations

```bash
php artisan migrate
```

The migrations create the user, session, cache, job, course, and prerequisite tables.

### 6. Install Frontend Dependencies

```bash
npm install
```

Build assets for a normal local run:

```bash
npm run build
```

For active frontend work, keep Vite running:

```bash
npm run dev
```

### 7. Open the App

With Laravel Herd, open:

```text
http://evaluator-v2.test
```

If Herd is not serving the site, check that the project is inside your Herd sites directory and that `APP_URL` matches the local URL.

## First Use Workflow

1. Open the Course Dashboard.
2. Create all course subjects.
3. Assign each subject to the correct semester.
4. Set prerequisites for subjects that require passed earlier courses.
5. Go to Evaluate Semester.
6. Select the semester being evaluated.
7. Enter the student's grades for that semester.
8. Submit the evaluation.

The system will mark whether the student can proceed. If a failed subject is a prerequisite for a future subject, that future subject is shown as blocked.

## Optional Workbook Import

The app also supports worksheet import through the Workbook Import panel.

Available templates:

- `/template.csv`
- `/template.xlsx`

## Useful Commands

```bash
php artisan migrate
php artisan test --compact
php artisan optimize:clear
npm run build
npm run dev
```

## Troubleshooting

If the page shows a Vite manifest error, run:

```bash
npm run build
```

If database-backed sessions, cache, or queues fail, make sure migrations have run:

```bash
php artisan migrate
```

If changes to `.env` do not apply, clear cached config:

```bash
php artisan optimize:clear
```
