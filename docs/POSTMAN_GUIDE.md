# Postman API Documentation

## Import Collection & Environment

### Step 1: Import Collection
1. Open Postman
2. Click **Import** button (top left)
3. Select file: `docs/Postman_Collection.json`
4. Click **Import**

### Step 2: Import Environment
1. Click **Environments** (left sidebar)
2. Click **Import**
3. Select file: `docs/Postman_Environment.json`
4. Click **Import**

### Step 3: Configure Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `base_url` | Your domain URL | `http://localhost:8000` or `https://api.yourdomain.com` |
| `api_token` | API authentication token | `abc123xyz789` |
| `plant_id` | Test plant ID | `4629` |

### Step 4: Select Environment
1. Top right dropdown in Postman
2. Select **"Qbits Solar API Environment"**

---

## Available Endpoints

### Plant APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/plant/list` | Get all plants with daily power data |
| GET | `/api/v1/plant/list/{{plant_id}}` | Get specific plant details |
| GET | `/api/v1/plant/info` | Get all plants with basic info |

### Inverter APIs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/inverter-faults` | Get inverter fault logs |

**Query Parameters for `/inverter-faults`:**
- `inverter_id` - Filter by inverter ID
- `plant_id` - Filter by plant ID
- `status` - Filter by status (-1 to skip)
- `limit` - Records per page (default: 20)

---

## Authentication

All endpoints require a `token` header:
```
token: {{api_token}}
```

---

## Files

- `Postman_Collection.json` - API requests with examples
- `Postman_Environment.json` - Environment variables
