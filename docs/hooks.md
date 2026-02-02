---
layout: default
title: Hooks System
nav_order: 4
---

# Hooks System

Velo provides a powerful **Hooks System** that allows you to extend the core logic without modifying the system files. This is useful for validation, data modification, side effects, and integrating with third-party services.

## Registering Hooks

Hooks are defined in `routes/hooks.php`. This file is automatically loaded by the application.

```php
use App\Domain\Hooks\Facades\Hooks;

Hooks::on('record.creating', function ($data, $context) {
    if ($context['collection']->name === 'posts') {
        $data['slug'] = Str::slug($data['title']);
    }
    return $data;
});
```

## Hook Types

There are two main types of hooks:

1.  **Filters (`apply`)**: These hooks receive data, can modify it, and **must return** the modified data. Used for `creating`, `updating`, `retrieved`.
2.  **Actions (`trigger`)**: These hooks receive context, perform side effects, and do not return data. Used for `created`, `updated`, `deleted`.

> As of version 0.1.0, apply hooks does not get validated by the `CollectionHandler`, so returned data will be instantly saved on to the database. Be careful!

## Available Hooks

### Record Hooks
Triggered during Record CRUD operations.

| Event | Type | Description |
| :--- | :--- | :--- |
| `record.retrieved` | Filter | Triggered when records are fetched (list or view). |
| `record.creating` | Filter | Triggered before a record is stored. Ideal for validation or formatting. |
| `record.created` | Action | Triggered after a record is successfully stored. |
| `record.updating` | Filter | Triggered before a record update is saved. |
| `record.updated` | Action | Triggered after a record update is saved. |
| `record.deleting` | Action | Triggered before a record is deleted. |
| `record.deleted` | Action | Triggered after a record is deleted. |

**Context**:
- `collection`: The `Collection` model instance.
- `record_id`: The ID of the record (if available).
- `original_data`: The data before changes (for updates).

### Auth Hooks
Triggered during authentication flows.

| Event | Type | Description |
| :--- | :--- | :--- |
| `auth.login` | Action | Triggered after successful login. |
| `auth.logout` | Action | Triggered on logout. |
| `auth.password_reset`| Action | Triggered when a password is reset. |

### Realtime Hooks

| Event | Type | Description |
| :--- | :--- | :--- |
| `realtime.connecting`| Action | Triggered when a client subscribes to a channel. |
| `realtime.broadcast` | Filter | Triggered before a message is sent. Return `null` to suppress. |
