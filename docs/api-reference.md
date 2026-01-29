---
layout: default
title: API Reference
nav_order: 3
has_children: true
---

# API Reference

The Velo API is organized around REST. Our API has predictable resource-oriented URLs, accepts form-encoded request bodies, returns JSON-encoded responses, and uses standard HTTP response codes, authentication, and verbs.

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
