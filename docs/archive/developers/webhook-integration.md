# Webhook Integration Guide

## Overview

Dot.Agents delivers real-time event notifications to your external systems via outbound webhooks. Every significant platform event (agent tasks, approvals, security incidents) can trigger an HTTP POST to your configured endpoint.

---

## Registering a Webhook Endpoint

### Via the Dashboard

1. Navigate to **Organization Settings → Integrations → Webhooks**
2. Click **Add Endpoint**
3. Enter your HTTPS URL and select the events to subscribe to
4. Copy the signing secret shown after creation

### Via API

```bash
POST /api/v1/webhooks
Authorization: Bearer {token}
Content-Type: application/json

{
  "url": "https://your-system.example.com/hooks/dotagents",
  "events": ["task.completed", "approval.requested", "security.event_detected"],
  "secret": null
}
```

The response includes a `signing_secret` — store it securely.

---

## Supported Events

| Event | Trigger |
|-------|---------|
| `task.created` | A new agent task is created |
| `task.completed` | An agent task finishes successfully |
| `task.failed` | An agent task fails or is rejected |
| `approval.requested` | A task requires human approval |
| `approval.approved` | A pending approval is approved |
| `approval.rejected` | A pending approval is rejected |
| `agent.deployed` | A new agent deployment is activated |
| `agent.decommissioned` | An agent deployment is shut down |
| `security.event_detected` | A security incident is logged |
| `security.event_resolved` | A security incident is resolved |
| `workflow.saved` | A workflow canvas is saved |
| `workflow.published` | A workflow is published for execution |

---

## Payload Structure

All events share a common envelope:

```json
{
  "id": "evt_01HZ...",
  "event": "task.completed",
  "organization_id": 42,
  "occurred_at": "2025-06-12T15:21:00Z",
  "data": {
    // event-specific payload
  }
}
```

### Example: `task.completed`

```json
{
  "id": "evt_01HZ...",
  "event": "task.completed",
  "organization_id": 42,
  "occurred_at": "2025-06-12T15:21:00Z",
  "data": {
    "task_id": 1001,
    "deployment_id": 5,
    "title": "Analyze Q3 Revenue",
    "status": "completed",
    "confidence_score": 92.0,
    "completed_at": "2025-06-12T15:21:00Z"
  }
}
```

---

## Verifying Signatures

Every webhook delivery includes an `X-Dot-Signature-256` header. Verify it before processing:

```php
// PHP verification example
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_DOT_SIGNATURE_256'] ?? '';
$secret    = env('WEBHOOK_SECRET');

$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (! hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

```python
# Python verification example
import hmac, hashlib

def verify(payload: bytes, header: str, secret: str) -> bool:
    expected = 'sha256=' + hmac.new(secret.encode(), payload, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, header)
```

---

## Retry Policy

| Attempt | Delay |
|---------|-------|
| 1 | Immediate |
| 2 | 30 seconds |
| 3 | 5 minutes |
| 4 | 30 minutes |
| 5 | 2 hours |

After 5 failed attempts the delivery is marked `failed` and the webhook endpoint is temporarily disabled. You can re-enable it from the dashboard.

---

## Responding to Webhooks

Your endpoint must return HTTP `2xx` within **10 seconds**. For slow processing, acknowledge immediately and process asynchronously:

```php
// Acknowledge immediately
http_response_code(200);
echo json_encode(['received' => true]);
fastcgi_finish_request(); // flush response

// Process asynchronously
dispatch(new ProcessWebhookJob($payload));
```

---

## Testing Webhooks Locally

Use the built-in test delivery tool from the dashboard, or via API:

```bash
POST /api/v1/webhooks/{id}/test
Authorization: Bearer {token}

{
  "event": "task.completed"
}
```

For local development, use [ngrok](https://ngrok.com) or [Hoppscotch](https://hoppscotch.io) to expose your local port.
