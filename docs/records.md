---
layout: default
title: Records API
parent: API Reference
nav_order: 1
---

# Records API

The Records API allows you to perform CRUD operations on the records within a collection.

**Base URL**: `/api/collections/{collection}/records`

> Currently the expand feature does not support nesting yet.


## List Records

Fetch a paginated list of records.

- **Method**: `GET`
- **Path**: `/`

### Query Parameters

| Parameter | Type | Description | Default |
| :--- | :--- | :--- | :--- |
| `page` | `int` | The page number to retrieve. | `1` |
| `per_page` | `int` | Number of records per page. | `15` |
| `filter` | `string` | Filter expression. | `null` |
| `sort` | `string` | Sort expression. | `null` |
| `expand` | `string` | Comma-separated list of relation fields to expand. | `null` |

### Filtering (`filter`)
Simple expression to filter results.
- **Operators**: `=`, `!=`, `>`, `<`, `>=`, `<=`, `LIKE`
- **Logic**: `AND`, `OR`
- **Format**: `field operator "value"`

Examples:
- `status = "active"`
- `age > 18 AND status = "active"`
- `title LIKE "Hello%"`

### Sorting (`sort`)
Comma-separated fields. Prefix with `-` for descending order.
- `created` (Ascending)
- `-created` (Descending)
- `-created,name` (Sort by created desc, then name asc)

### Expanding (`expand`)
Expand relation fields to include the full referenced record data.
- `author`
- `author,category`

### Response
```json
{
    "data": [
        {
            "id": "...",
            "collection_id": "...",
            "data": {
                "title": "Hello World",
                "status": "active"
            },
            "created": "...",
            "updated": "..."
        }
    ],
    "links": {...},
    "meta": {...}
}
```

## View Record

Fetch a single record by ID.

- **Method**: `GET`
- **Path**: `/{id}`

### Query Parameters
| Parameter | Type | Description |
| :--- | :--- | :--- |
| `expand` | `string` | Comma-separated list of relation fields to expand. |

## Create Record

Create a new record.

- **Method**: `POST`
- **Path**: `/`
- **Headers**: `Content-Type: application/json`

### Payload
JSON object containing the fields to store.
```json
{
    "title": "My New Post",
    "status": "draft"
}
```
*Note: view rules defined in the collection fields will be applied.*

### Response
Returns the created record.

## Update Record

Update an existing record.

- **Method**: `PUT`
- **Path**: `/{id}`
- **Headers**: `Content-Type: application/json`

### Payload
JSON object containing the fields to update. Partial updates are supported.
```json
{
    "status": "published"
}
```

### Response
Returns the updated record.

## Delete Record

Delete a record.

- **Method**: `DELETE`
- **Path**: `/{id}`

### Response
`204 No Content`

Next up: [Authentication](authentication.md)