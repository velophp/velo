---
layout: default
title: Authentication
parent: API Reference
nav_order: 2
---

# Authentication

Velo simplifies user authentication by treating users as records in an **Auth Collection**.
Velo uses stateful authentication, which means it must verify your token on every request. You can manage authentication sessions in the Admin Panel under the System section. Authentication is handled through `AuthMiddleware`.
By using stateful authentication approach, you can also instantly terminate a user's session or session for a separate device.

## Auth Collection

Any collection with the type `Auth` acts as a user provider. You can have multiple auth collections (e.g., `users`, `admins`, `customers`) completely separated from each other.

By default, an Auth collection has the following fields:
- `email`
- `password`
- `verified` (bool)

## Authentication Methods

Velo supports multiple ways to authenticate.

### 1. Password Authentication

The standard email/password flow.

- **Method**: `POST`
- **Path**: `/api/collections/{collection}/auth/authenticate-with-password`

#### Payload
```json
{
    "identifier": "user@example.com",
    "password": "secretpassword"
}
```

#### Response
```json
{
    "token": "...",
    "data": {
        "id": "...",
        "email": "..."
    }
}
```

### 2. OTP Authentication

Passwordless login via email codes.

#### Request OTP
- **Method**: `POST`
- **Path**: `/api/collections/{collection}/auth/request-auth-otp`

Payload:
```json
{
    "email": "user@example.com"
}
```

#### Login with OTP
- **Method**: `POST`
- **Path**: `/api/collections/{collection}/auth/authenticate-with-otp`

Payload:
```json
{
    "email": "user@example.com",
    "otp": "123456"
}
```

## Token Management

On successful authentication, the API returns a Bearer Token. This token should be included in the `Authorization` header (`Bearer <token>`) for subsequent requests.

### Get Current User
- **Method**: `GET`
- **Path**: `/api/collections/{collection}/auth/me`

### Logout
Invalidate the current token.
- **Method**: `POST`
- **Path**: `/api/collections/{collection}/auth/logout`

### Logout All
Invalidate *all* tokens for the user (sign out everywhere).
- **Method**: `POST`
- **Path**: `/api/collections/{collection}/auth/logout-all`

## Account Management

### Forgot Password

#### 1. Request Reset
- **Method**: `POST`
- **Path**: `/api/collections/{collection}/auth/forgot-password`

Payload:
```json
{
    "email": "user@example.com"
}
```

#### 2. Confirm Reset
- **Method**: `POST`
- **Path**: `/api/collections/{collection}/auth/confirm-forgot-password`

Payload:
```json
{
    "otp": "123456",
    "new_password": "newpassword",
    "new_password_confirm": "newpassword"
}
```
