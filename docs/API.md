# Qbits Solar API - Developer Documentation

> **Base URL:** `https://qbits.quickestimate.co/api/v1`
> **Protocol:** HTTPS
> **Content-Type:** `application/json`

## Table of Contents

- [Quick Start](#quick-start)
- [Authentication](#authentication)
- [Plant APIs](#plant-apis)
  - [Get All Plants List](#1-get-all-plants-list)
  - [Get Plant Details](#2-get-plant-details)
  - [Get All Plants Info](#3-get-all-plants-info)
- [Inverter APIs](#inverter-apis)
  - [Get Inverter Faults](#4-get-inverter-faults)
- [Error Handling](#error-handling)
- [Rate Limits](#rate-limits)

---

## Quick Start

All API requests require an authentication token in the request header.

### Example Request
```bash
curl -X GET "https://qbits.quickestimate.co/api/v1/plant/list" \
  -H "token: YOUR_API_TOKEN"
```

### Response Format
Every response follows this structure:
```json
{
    "status": true,
    "message": "Success message",
    "data": { ... }
}
```

---

## Authentication

| Header | Type | Required | Description |
|--------|------|----------|-------------|
| `token` | string | Yes | API authentication token |

Tokens are validated against the `clients` table where `user_flag = 1`.

**401 Unauthorized** is returned when:
- Token header is missing
- Token is invalid or expired
- Client `user_flag` is not `1`

---

## Plant APIs

### 1. Get All Plants List

**Endpoint:** `GET /plant/list`

Retrieves all plants with current day's solar power data. Data is cached for **100 seconds**.

#### Request
```bash
curl -X GET "https://qbits.quickestimate.co/api/v1/plant/list" \
  -H "token: YOUR_API_TOKEN"
```

#### Response - Success (200)
```json
{
    "status": true,
    "message": "Plant list fetched successfully",
    "data": {
        "plants": [
            {
                "id": 689,
                "plant_id": 4629,
                "name": "RAVINAVEKARIYA A101",
                "country": "India",
                "longitude": "72.7811642",
                "latitude": "21.1935024",
                "peak_power": [
                    {
                        "recordTime": "05:54:23",
                        "irradiation": "0",
                        "acMomentaryPower": "0.0"
                    }
                ],
                "total_energy": "11.69"
            }
        ]
    }
}
```

#### Response Fields
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Plant record ID |
| `plant_id` | integer | Unique plant number |
| `name` | string | Plant name |
| `country` | string | Fixed value `"India"` |
| `longitude` | string | GPS longitude |
| `latitude` | string | GPS latitude |
| `peak_power` | array[] | Today's power readings array |
| `peak_power[].recordTime` | string | Reading time (HH:MM:SS) |
| `peak_power[].irradiation` | string | Irradiation value |
| `peak_power[].acMomentaryPower` | string | AC power in kW |
| `total_energy` | string | Today's total energy (kWh) |

#### Notes
- Only returns plants with solar power logs for **today**
- `peak_power` is auto-decoded from JSON string
- Results are scoped to the authenticated client's company code

---

### 2. Get Plant Details

**Endpoint:** `GET /plant/list/{id}`

Retrieves a single plant's detailed data for today. Data is cached for **15 minutes (900 seconds)**.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Plant number (`plant_no`) |

#### Request
```bash
curl -X GET "https://qbits.quickestimate.co/api/v1/plant/list/4629" \
  -H "token: YOUR_API_TOKEN"
```

#### Response - Success (200)
```json
{
    "status": true,
    "message": "Plant details fetched successfully",
    "data": {
        "id": 689,
        "plant_id": 4629,
        "name": "RAVINAVEKARIYA A101",
        "country": "India",
        "longitude": "72.7811642",
        "latitude": "21.1935024",
        "peak_power": [...],
        "total_energy": "11.69"
    }
}
```

#### Error Responses
| Code | Body | When |
|------|------|------|
| 400 | `{"success":false,"message":"Plant No is required"}` | Missing `id` parameter |
| 404 | `{"success":false,"message":"Plant not found or invalid token"}` | Plant doesn't exist or unauthorized |

---

### 3. Get All Plants Info

**Endpoint:** `GET /plant/info`

Retrieves all plants with **basic info and production data** directly from `plant_infos` table (no solar log join). Data is cached for **100 seconds**.

#### Request
```bash
curl -X GET "https://qbits.quickestimate.co/api/v1/plant/info" \
  -H "token: YOUR_API_TOKEN"
```

#### Response - Success (200)
```json
{
    "status": true,
    "message": "Plant info fetched successfully",
    "data": {
        "plants": [
            {
                "id": 689,
                "user_id": 123,
                "plant_id": 4629,
                "name": "RAVINAVEKARIYA A101",
                "country": "India",
                "longitude": "72.7811642",
                "latitude": "21.1935024",
                "capacity": "5.00",
                "peak_power": "2.50",
                "day_production": "11.69",
                "total_production": "4520.30",
                "month_production": "345.80",
                "year_production": "2180.50",
                "location": "Surat, Gujarat",
                "plant_status": 1
            }
        ]
    }
}
```

#### Response Fields
| Field | Type | Source Column | Description |
|-------|------|-------------|-------------|
| `id` | integer | `plant_infos.id` | Record ID |
| `user_id` | integer | `plant_infos.user_id` | Client ID |
| `plant_id` | integer | `plant_infos.plant_no` | Plant number |
| `name` | string | `plant_infos.plant_name` | Plant name |
| `country` | string | static `"India"` | Fixed country |
| `longitude` | string | `clients.longitude` | GPS longitude |
| `latitude` | string | `clients.latitude` | GPS latitude |
| `capacity` | string | `plant_infos.capacity` | Capacity (kW) |
| `peak_power` | string | `plant_infos.acpower` | AC peak power (kW) |
| `day_production` | string | `plant_infos.eday` | Today (kWh) |
| `total_production` | string | `plant_infos.etot` | Lifetime (kWh) |
| `month_production` | string | `plant_infos.month_power` | This month (kWh) |
| `year_production` | string | `plant_infos.year_power` | This year (kWh) |
| `location` | string | `plant_infos.remark1` | Address |
| `plant_status` | integer | `plant_infos.plantstate` | Status code |

---

## Inverter APIs

### 4. Get Inverter Faults

**Endpoint:** `GET /inverter-faults`

Retrieves inverter fault logs scoped to the authenticated client's company code.

#### Query Parameters
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `inverter_id` | integer | No | - | Filter by inverter ID |
| `plant_id` | integer | No | - | Filter by plant ID |
| `status` | integer | No | - | Filter by status. Use `-1` to skip filter |
| `limit` | integer | No | 20 | Records per page |

#### Request Examples
```bash
# All faults
curl -X GET "https://qbits.quickestimate.co/api/v1/inverter-faults" \
  -H "token: YOUR_API_TOKEN"

# Filter by status=1, limit 10
curl -X GET "https://qbits.quickestimate.co/api/v1/inverter-faults?status=1&limit=10" \
  -H "token: YOUR_API_TOKEN"

# Filter by plant
curl -X GET "https://qbits.quickestimate.co/api/v1/inverter-faults?plant_id=4629" \
  -H "token: YOUR_API_TOKEN"
```

#### Response - Success (200)
```json
{
    "status": true,
    "message": "Inverter fault list fetched successfully",
    "data": {
        "faults": {
            "data": [
                {
                    "id": 1,
                    "inverter_id": 101,
                    "plant_id": 4629,
                    "status": 1,
                    "itype": 1,
                    "inverter_sn": "INV123456",
                    "stime": "2024-01-15 08:30:00",
                    "etime": "2024-01-15 10:00:00",
                    "meta": null,
                    "message_en": ["Grid fault", "Overvoltage"],
                    "inverter": {
                        "id": 101,
                        "plant_id": 4629,
                        "inverter_no": "INV001",
                        "model": "Model X",
                        "state": 1,
                        "plant": {
                            "plant_name": "RAVINAVEKARIYA A101",
                            "plant_no": 4629,
                            "country": "India",
                            "city": "Surat"
                        }
                    }
                }
            ],
            "current_page": 1,
            "first_page_url": "https://qbits.quickestimate.co/api/v1/inverter-faults?status=1&limit=10&page=1",
            "next_page_url": null,
            "prev_page_url": null,
            "per_page": 10
        }
    }
}
```

#### Response Fields
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Fault record ID |
| `inverter_id` | integer | Inverter ID |
| `plant_id` | integer | Plant ID |
| `status` | integer | Fault status code |
| `itype` | integer | Fault type code |
| `inverter_sn` | string | Inverter serial number |
| `stime` | datetime | Fault start time |
| `etime` | datetime | Fault end time |
| `meta` | array/object | Additional fault metadata |
| `message_en` | array | English fault messages |
| `inverter` | object | Inverter details with nested `plant` |

#### Pagination
This endpoint uses `simplePaginate`. Pagination links (`next_page_url`, `prev_page_url`) are included in the response. Query parameters are preserved in pagination URLs.

---

## Error Handling

### Standard Error Response
```json
{
    "success": false,
    "message": "Error description"
}
```

> **Note:** Success responses use `"status": true` while error responses use `"success": false`. Check both keys in your integration.

### HTTP Status Codes

| Code | Meaning | Common Causes |
|------|---------|---------------|
| 200 | OK | Request successful |
| 400 | Bad Request | Missing required parameters (e.g., plant `id`) |
| 401 | Unauthorized | Missing/invalid token or `user_flag != 1` |
| 404 | Not Found | Plant or resource not found |
| 500 | Server Error | Internal application error |

### Error Scenarios

#### Missing Token
```json
{
    "success": false,
    "message": "Token is required"
}
```

#### Invalid Token
```json
{
    "success": false,
    "message": "Invalid or expired token"
}
```

#### No Plants Found
```json
{
    "status": true,
    "message": "No plants found",
    "data": { "faults": [] }
}
```

---

## Rate Limits

| Endpoint | Cache Duration |
|----------|---------------|
| `GET /plant/list` | 100 seconds |
| `GET /plant/list/{id}` | 15 minutes (900 seconds) |
| `GET /plant/info` | 100 seconds |
| `GET /inverter-faults` | No cache (live data) |

---

## Data Models

### Plant Info (`plant_infos` table)
Key columns mapped in APIs:
- `plant_no` → `plant_id`
- `plant_name` → `name`
- `acpower` → `peak_power`
- `eday` → `day_production` / `total_energy`
- `etot` → `total_production`
- `month_power` → `month_production`
- `year_power` → `year_production`
- `remark1` → `location`
- `plantstate` → `plant_status`

### Inverter Fault (`inverter_faults` table)
- `status`: `0` = resolved, `1` = active (varies by implementation)
- `itype`: Fault category/type code
- `stime` / `etime`: Fault duration timestamps
- `message_en`: JSON array of fault description strings

---

**Last Updated:** June 2026  
**Contact:** For API access, contact your Qbits administrator.
