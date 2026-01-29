---
layout: default
title: Realtime
parent: API Reference
nav_order: 3
---

# Realtime

Velo pushes data changes to connected clients in real-time using WebSockets.
By default velo supports two main driver, Laravel Reverb and Pusher. You can choose your own driver and setup your frontend approach.

Behind the scenes, Velo stores all of the realtime connection in the `realtime_connections` table, and when a record triggers a broadcast event, Velo will check the `realtime_connections` table to see if there are any clients subscribed to the collection and send the event to them. It will also check if there are any filters defined for the collection and send the event to the clients that match the filter. The filtering used a combination of the list rule and the realtime filter defined when creating a connection. The filter string is parsed and checked against the record data. It will also interpolate the @request.auth from the `realtime_connections` join query as the authenticated user.

## Subscribing to Changes

Clients can subscribe to specific collections to receive updates when records are created, updated, or deleted.

### Endpoint
- **Method**: `POST`
- **Path**: `/api/realtime/subscribe`

#### Payload
```json
{
    "collection": "posts",
    "socket_id": "1234.5678",
    "filter": "status = 'active'"
}
```

| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `collection` | `string` | Yes | The name or ID of the collection. |
| `socket_id` | `string` | No | The socket ID (for excluding self from broadcasts). |
| `filter` | `string` | No | A filter expression to only receive updates for matching records. |

#### Response
Returns the channel name to subscribe to.
```json
{
    "channel_name": "velo.realtime.posts"
}
```

### Channels

The subscribe endpoint returns a unique `channel_name`. Clients should use this channel name to listen for events.

**Format**: `{prefix}{channel_name}`

*Channel prefix is configurable in `config/velo.php` (default is `velo.realtime.`).*

## Events

When data changes, an event is broadcasted to the channel.

### Event Types

| Event Name | Description |
| :--- | :--- |
| `created` | Fired when a new record is created. |
| `updated` | Fired when an existing record is updated. |
| `deleted` | Fired when a record is deleted. |

### Event Payload

**Create / Update / Delete**
```json
{
    "action": "created", // [created, updated, deleted]
    "record": {
        "id": "...",
        "title": "New Post",
        "created": "...",
        "updated": "..."
    }
}
```
