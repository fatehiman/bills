# Bills API - usage guide

The Bills API is a **bill API gateway**. You send a bill type and a number. We ask the bill providers
(for example Zohal) and give you back the **Bill ID** (`bill_no`) and the **Payment ID** (`payment_no`).
With these two numbers you can pay the bill in any bank app.

- Base URL: `https://bills.kimiasoft.com/api`
- Current version: `v1` (`/api/v1/...`)
- Format: JSON in, JSON out. All text is English.
- Amounts are in **Rial** (integer).

## 1. Get an API token

By default a user has **no** token.

1. Log in at `https://bills.kimiasoft.com/mainadmin`.
2. Open **Account & API**.
3. Click **Create token**. Copy it now. It is shown **only once** (we keep only a hash).
4. To get a new token, click **Rotate token**. The old token stops working at once.
5. **Remove token** disables API access for your user.

A token looks like `bl_AbCd...` (43 characters).

## 2. Authentication

Send the token in the `Authorization` header on every call:

```
Authorization: Bearer bl_YOUR_TOKEN
```

Without a valid token you get `401 unauthenticated`.

## 3. Response format

Success:

```json
{ "ok": true, "data": { } }
```

Error:

```json
{ "ok": false, "error": { "code": "validation_failed", "message": "The given data is not valid.", "details": { "identifier": ["..."] } } }
```

| HTTP | `error.code` | Meaning |
|---|---|---|
| 401 | `unauthenticated` | Token is missing or wrong |
| 404 | `not_found` | Not found (also used for bills of other users) |
| 405 | `method_not_allowed` | Wrong HTTP method |
| 422 | `validation_failed` | Bad input. See `error.details` |
| 422 | `invalid_identifier` | The bill number is wrong (inquiry) |
| 429 | `rate_limited` | More than 60 requests per minute per token |
| 422 | `unknown_type`, `unknown_provider`, `provider_not_supported` | Wrong `type` or `provider` |
| 424 | `providers_failed` | All providers failed. `error.details.attempts` shows why |
| 424 | `not_available` | No provider is configured for this bill type |

Note: we never use HTTP 5xx for provider problems, because CDNs replace 5xx bodies with their own page. A provider problem is `424`.

## 4. Bill types

| `type` | Name | `identifier` is |
|---|---|---|
| `electricity` | Electricity | Electricity bill ID (6-20 digits) |
| `water` | Water | Water bill ID (6-20 digits) |
| `gas` | Gas | Gas bill ID (6-20 digits). Ghabzino needs the gas participation code instead |
| `landline` | Landline phone | Phone number with area code, like `02112345678` |
| `mci` | MCI (Hamrah-e Aval) | Mobile number, like `09121234567` |
| `irancell` | Irancell | Mobile number |
| `rightel` | Rightel | Mobile number (Zohal only) |

Persian and Arabic digits, spaces and dashes in `identifier` are accepted. We clean them.

Use `GET /api/v1/bill-types` to see the live list and which providers serve each type.

## 5. Providers, mapping and fallback

- A **provider** is an outside service that knows the bills (now: `zohal` and `ghabzino`).
- The admin decides which providers serve which bill type, and in which order.
  Example: `water -> zohal, then ghabzino`.
- **The `provider` field is optional everywhere.**
  - If you **do not** send it, we use the admin's list in order. If the first provider cannot answer
    (down, no credit, timeout), we **automatically try the next one** (fallback).
  - If you **send** it, only that provider is used. No fallback.
- A wrong bill number (`invalid_identifier`) stops at once. Other providers would say the same.
- "There is no payable bill" is a normal answer (`has_bill: false`). It does not start a fallback.

## 6. Endpoints

### 6.1 `GET /api/v1/bill-types`

Lists bill types.

```bash
curl -H "Authorization: Bearer $TOKEN" https://bills.kimiasoft.com/api/v1/bill-types
```

```json
{ "ok": true, "data": [
  { "type": "electricity", "label": "Electricity", "identifier_label": "Electricity bill ID", "providers": ["zohal"] },
  { "type": "mci", "label": "MCI (Hamrah-e Aval)", "identifier_label": "Mobile number", "providers": ["zohal"] }
] }
```

### 6.2 `POST /api/v1/inquiry` (the gateway)

Asks for the payable bills **now**. Nothing is saved. (`GET` with query parameters also works.)

| Field | Required | Description |
|---|---|---|
| `type` | yes | A bill type from the table above |
| `identifier` | yes | The bill number or phone number |
| `provider` | no | Force one provider. Leave out to use the admin order with fallback |

```bash
curl -X POST https://bills.kimiasoft.com/api/v1/inquiry \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"type":"mci","identifier":"09121234567"}'
```

```json
{
  "ok": true,
  "data": {
    "type": "mci",
    "identifier": "09121234567",
    "provider": "zohal",
    "has_bill": true,
    "bills": [
      { "bill_no": "333", "payment_no": "444", "amount": 200000, "term": "final", "extra": {} },
      { "bill_no": "111", "payment_no": "222", "amount": 100000, "term": "mid",   "extra": {} }
    ],
    "failed_providers": []
  }
}
```

- `bills` is a list. Mobile and landline bills can have two terms: `final` and `mid` (the inquiry returns both; saved bills keep `mid` only if `include_mid_term` is on). Other types have one bill and `term` is `null`.
- `provider` is the provider that answered. `failed_providers` lists the ones that failed before it (fallback), like `[{"provider":"zohal","error":"..."}]`.
- `has_bill: false` and `bills: []` mean there is nothing to pay.
- `extra` has more info about the bill, when the provider gives it. Every key is optional, and the
  keys are the same for all providers. Text values keep the provider's language (for example a Persian
  name or address). Dates are Gregorian `YYYY-MM-DD` (Jalali dates from the providers are converted).

  | Key | Meaning |
  |---|---|
  | `full_name`, `address` | Subscriber name and address |
  | `previous_date`, `current_date` | Start and end of the billing period (previous and current meter reading) |
  | `issue_date` | Date the bill was issued |
  | `payment_date` | Payment deadline |
  | `billing_days` | Number of days in the period |
  | `consumption`, `consumption_unit` | Usage in the period (`kWh` for electricity, `m3` for gas) |
  | `previous_meter_reading`, `current_meter_reading` | Meter readings (gas) |
  | `consumption_mid_load`, `consumption_peak`, `consumption_low` | Electricity usage by load band |
  | `usage_type` | Tariff or consumption type (for example household) |
  | `distribution_company`, `subscription_id`, `meter_serial` | Electricity and gas subscription info |
  | `cycle` | Billing cycle of a phone bill |
  | `bill_pdf_url` | Link to the bill PDF |
  | `details` | Everything else the provider sent, with the provider's own keys (nothing is dropped) |

  The main data of a bill is always `bill_no`, `payment_no` and `amount`. Do not depend on `extra`.

### 6.3 Saved bills

Saved bills are fetched automatically on a schedule. **Every user sees only their own bills.**
A bill of another user answers `404`.

#### `GET /api/v1/bills`
List your bills.

#### `POST /api/v1/bills`
Create a bill and fetch it once.

| Field | Required | Description |
|---|---|---|
| `title` | yes | Your name for the bill (max 120) |
| `bill_type` | yes | Bill type |
| `identifier` | yes | Bill or phone number |
| `provider` | no | If left out, the first provider of the admin list is saved with the bill |
| `include_mid_term` | no | `true` to also keep Mid term bills of phone/mobile. Default `false` |
| `period_days` | no | Auto fetch period: `0` (manual only), `1`, `3`, `7`, `15`, `30` (default), `60` |

```bash
curl -X POST https://bills.kimiasoft.com/api/v1/bills \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"title":"Home water","bill_type":"water","identifier":"1234567","period_days":30}'
```

Answer `201`:

```json
{
  "ok": true,
  "fetch": { "status": "ok", "new": 1, "error": null },
  "data": {
    "id": 12, "title": "Home water", "type": "water", "identifier": "1234567", "provider": "zohal",
    "period_days": 30, "last_status": "ok", "last_error": null,
    "last_fetched_at": "2026-10-11T12:00:00+00:00", "next_fetch_at": "2026-11-10T12:00:00+00:00",
    "unpaid_items": [
      { "id": 5, "bill_no": "1234567", "payment_no": "7654321", "amount": 90000, "term": null,
        "provider": "zohal", "extra": {}, "paid": false, "paid_at": null, "fetched_at": "2026-10-11T12:00:00+00:00" }
    ]
  }
}
```

`fetch.status` is `ok` (bills found), `no_bill` (nothing to pay) or `error` (see `fetch.error`).
A failed fetch is retried after 6 hours.

#### `GET /api/v1/bills/{id}`
One bill with its unpaid items.

#### `PATCH /api/v1/bills/{id}`
Change `title`, `provider`, `period_days` or `include_mid_term`. (Type and identifier cannot change.)

#### `DELETE /api/v1/bills/{id}`
Delete the bill and its history.

#### `POST /api/v1/bills/{id}/fetch`
Fetch now. The answer has `fetch` and the updated `data`.

#### `GET /api/v1/bills/{id}/items`
History of fetched bills. Optional `?paid=0` or `?paid=1` filter.

#### `PATCH /api/v1/bills/{id}/items/{itemId}`
Mark an item as paid or not paid: `{"paid": true}`.

## 7. Limits

- 60 requests per minute per token (`429` after that).
- Each inquiry calls a paid outside provider. Please do not call it in a tight loop. Use saved bills with auto fetch when you can.

## 8. Code examples

### curl

```bash
export TOKEN=bl_YOUR_TOKEN
curl -s -H "Authorization: Bearer $TOKEN" \
  "https://bills.kimiasoft.com/api/v1/inquiry?type=electricity&identifier=9521668804120"
```

### PHP

```php
$ch = curl_init('https://bills.kimiasoft.com/api/v1/inquiry');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['type' => 'mci', 'identifier' => '09121234567']),
]);
$res = json_decode(curl_exec($ch), true);
if ($res['ok'] && $res['data']['has_bill']) {
    foreach ($res['data']['bills'] as $b) {
        echo "Bill ID {$b['bill_no']}  Payment ID {$b['payment_no']}  Amount {$b['amount']}\n";
    }
}
```

### JavaScript (Node 18+ or browser)

```js
const res = await fetch('https://bills.kimiasoft.com/api/v1/inquiry', {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
  body: JSON.stringify({ type: 'water', identifier: '1234567' }),
});
const json = await res.json();
if (!json.ok) throw new Error(json.error.message);
console.log(json.data.bills);
```

Do not put the token in browser code that other people can open. Call the API from your server.

### Python

```python
import requests
r = requests.post(
    "https://bills.kimiasoft.com/api/v1/inquiry",
    headers={"Authorization": f"Bearer {token}"},
    json={"type": "gas", "identifier": "123456789"},
    timeout=60,
)
data = r.json()
print(data["data"]["bills"] if data["ok"] else data["error"])
```

## 9. Tips

- Always check `ok` first, then `has_bill`.
- Store `bill_no` and `payment_no` as **strings**. They can start with zero.
- Timeouts: an inquiry can take up to about 30 seconds per provider. Use a client timeout of 60 seconds.
