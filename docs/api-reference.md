---
layout: default
title: API Reference
nav_order: 3
has_children: true
---

# API Reference

API is used by the outside requests to do something inside the database.

## Base URL

All API requests should be prefixed with:

```
http://your-app-url.com/api
```

## Response Format

All responses are returned in JSON format.

### Success Response
```json
{
    "data": { ... }
}
```

### Error Response
```json
{
    "message": "Resource not found",
    "errors": { ... } // Optional validation errors
}
```

## Topics

- **[Records API](records.md)**: CRUD operations for Collection Records (List, View, Create, Update, Delete).
- **[Authentication API](authentication.md)**: Login, Registration, OTP, and Password Management.
- **[Realtime API](realtime.md)**: WebSockets interactions for real-time updates.

## Common Headers

| Header | Value | Description |
| :--- | :--- | :--- |
| `Accept` | `application/json` | **Required**. Ensures Laravel returns JSON responses instead of redirects. |
| `Content-Type` | `application/json` | Required for POST/PUT requests with JSON body. |
| `Authorization` | `Bearer <token>` | Required for authenticated endpoints. |

Next up: [Records API](records.md)