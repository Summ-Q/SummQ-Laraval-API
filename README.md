# SummQ Laravel API

The SummQ API is the backend infrastructure for the SummQ mobile application. It handles user authentication, flashcard generation, and spaced-repetition study logging.

## 1. Overview & App Flow

* The Flutter frontend authenticates requests via Laravel Sanctum.
* Users create study decks or upload PDF files.
* The API passes the PDFs or text notes to an internal Python AI engine to generate flashcards.
* During study sessions, the API logs review scores (1-4) and fetches the next review interval from a Python Data Science model.
* The calculated intervals and progress are updated and stored in the PostgreSQL database.

## 2. Tech Stack & Libraries

* Laravel 11
* PostgreSQL (hosted on Supabase)
* Laravel Sanctum (Token-based authentication)
* Laravel HTTP Client / Guzzle (Internal microservice communication)
* Deployed on Vercel (Serverless PHP)

## 3. Directory Structure

* `app/Http/Controllers`: Contains the Auth, Deck, Flashcard, and Study controllers.
* `app/Models`: Contains the Deck, Flashcard, StudyProgress, and ReviewLog data models.
* `routes/api.php`: Defines the exposed endpoints and Sanctum middleware groups.

## 4. Local Installation & Setup

Clone the repository and install the PHP dependencies:

```bash
git clone https://github.com/Summ-Q/SummQ-Laraval-API.git
cd summq-laravel-api
composer install
```

Set up your environment variables and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your `.env` file with your local or remote Supabase credentials. Use the Session pooler (port 5432) for local development to maintain a persistent connection.

Run the database migrations and start the local server:

```bash
php artisan migrate
php artisan serve
```

## 5. Deployment Notes (Vercel & Supabase)

Vercel operates on a strictly read-only filesystem. You must provide specific environment variables in the Vercel dashboard to map all framework caches (config, routes, views, packages) to the temporary `/tmp` directory.

Supabase utilizes PgBouncer for connection pooling (port 6543), which operates in Transaction Mode. This mode drops standard prepared statements. To prevent database transaction failures during `DB::transaction()` calls, you must explicitly enable emulated prepares in `config/database.php`:

```php
'options' => [
    \PDO::ATTR_EMULATE_PREPARES => true,
],
```

## 6. Usage / Core Endpoints

All protected routes require a valid Sanctum Bearer Token in the `Authorization` header and the `Accept: application/json` header.

* POST `/register` & `/login` - Authenticates the user and returns a Bearer Token.
* GET `/decks` & POST `/decks` - Retrieves user decks or creates a new deck.
* POST `/decks/{deck}/generate` - Accepts a PDF file or text string and returns AI-generated flashcards.
* GET `/decks/{deck}/study` - Retrieves a queue of flashcards currently due for review.
* POST `/reviews/{flashcard}` - Accepts a study score (1-4), records the review, and calculates the next due date.