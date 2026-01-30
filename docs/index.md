---
layout: default
title: Introduction
nav_order: 1
---

# Introduction

Velo is a Backend-as-a-Service (BaaS) kit for Laravel. It provides a dynamic database layer (Collections) on top of a standard Laravel application, allowing you to manage schemas at runtime while preserving the ability to write custom PHP code.

It is heavily inspired by [PocketBase](https://pocketbase.io), but built natively for the Laravel ecosystem.

## Demo

- Velo: [Demo](https://demo.velophp.com)
- Flashcard (Uses Velo as its backend): [Flashcard](https://kevintherm.github.io/velo-flashcard)

## Quick Start

Prerequisites:
- PHP >8.4
- Composer
- MySQL
- Node/Bun

```bash
composer create-project kevintherm/velo my-velo-backend
cd my-velo-backend
composer install
cp .env.example .env
php artisan key:generate

# configure your db vars

bun install # or npm install
bun run build # or npm run build

php artisan migrate

php artisan serve
```

Velo should now be available on http://localhost:8000

## Documentation

### Core
- **[Concepts & Architecture](core-concepts.md)**: How Collections, Records, and Fields work under the hood.
- **[API Reference](api-reference.md)**: The HTTP API specification for interacting with your data.

### Features
- **[Authentication](authentication.md)**: Built-in user management, OTP support, and token handling.
- **[Realtime](realtime.md)**: WebSocket events for record changes (Laravel Reverb / Pusher).
- **[Hooks](hooks.md)**: Intercept and modify data flows using standard PHP closures.
