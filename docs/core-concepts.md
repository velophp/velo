---
layout: default
title: Core Concepts
nav_order: 2
---

# Core Concepts

Velo is built around the concept of dynamic **EAV (Entity-Attribute-Values)**. Unlike a traditional Laravel application where you define schema using Migrations and Eloquent Models for each entity, Velo allows you to define your schema at runtime.

## Collections

A **Collection** is like a table in a standard database. It defines the structure and behavior of the data interaction.

There are different types of collections that you can use :
- **Base**: Standard collections for storing data (e.g., `posts`, `products`).
- **Auth**: Special collections that act as user providers (e.g., `users`). They come with built-in authentication capabilities.
- **View**: (Coming Soon) Read-only collections based on SQL queries.

### Schema Definition
Each collection has many fields that are defined as a relationship. To get a collection's fields you can get it through `$collection->fields()`.

## Field Types

Velo supports various field types that you can use in your application:

- **Text**: Simple string values.
- **RichText**: Rich text content. Same as Text but served with rich text editor on the admin panel.
- **Bool**: Boolean toggles.
- **Number**: Integers or floats.
- **Email**: Email addresses.
- **Url**: URLs. (Coming soon)
- **Select**: Single choice from predefined options. (Coming soon)
- **Datetime**: Date and time values.
- **File**: File uploads (see File Handling).
- **Relation**: References to records in other collections.
- **Json**: Store arbitrary JSON data. (Coming soon)

## Records

A **Record** is an instance of data within a Collection. Internally, Velo uses a `records` table with a JSON `data` column to store these dynamic attributes.
You can create index on a field to match the performance of a standard SQL schema.

```php
// Accessing record data
$title = $record->data->get('title');
$record->data->put('status', 'active');
$record->save();
```

## API Rules

Collections use **API Rules** to control access. These are expression strings that evaluate to true or false.
Rule expressions are similar to other BaaS rule expressions, but internally they're just parsed to PHP scripts using [Symfony ExpressionLanguage](https://symfony.com/doc/current/components/expression_language.html). For convenience, Velo will parse a single `=` as `==`.

- **List**: Who can list/search records.
- **View**: Who can view a specific record.
- **Create**: Who can create new records.
- **Update**: Who can edit existing records.
- **Delete**: Who can delete records.

Rules can reference the current user (`@request.auth.id`) and the record being accessed (`id`, `created`, `name`).

Example:
```
// Only the creator can update the record
@request.auth.id = user
```
