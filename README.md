# OCR Capture

An authenticated Laravel application for capturing or uploading an image, attaching the signed-in user's name and optional browser location, and forwarding it to an OCR service.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm

## Setup

Run these commands from the application directory containing `artisan` and `package.json` (`first-OCR-using-php-main`),
```sh
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8083 --no-reload
```

### Windows PowerShell


```powershell

php --version
php artisan serve

Register an account, sign in, and open `/dashboard` to use the OCR capture page. RUn php artisan db:seed and  use:

Email: test@example.com
Password: password to login

## OCR service configuration

Set `OCR_SERVICE_URL` in `.env` to the OCR endpoint. It defaults to `http://127.0.0.1:5000/process_image/upload-image`. The service receives a multipart `image` plus `longitude`, `latitude`, and the authenticated user's `username`.

Optionally set `GOOGLE_MAPS_GEOCODING_KEY` to enable reverse-geocoded address display. Restrict this browser key to the application's allowed referrers and the required Google Maps API.


