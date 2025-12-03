# HVAC Monitor Copilot Instructions

## Project Overview
A geothermal HVAC system monitor running on a Raspberry Pi that tracks 3-stage heating/cooling cycles with temperature/humidity sensing and remote data logging.

## Architecture

### Three-Layer System
1. **Raspberry Pi Hardware Layer** (`pi/hvac.py`): Detects HVAC stage changes via GPIO pins, reads AM2320 temperature/humidity sensor over I2C, logs to local SQLite database
2. **Data Persistence Layer** (`pi/hvac_database.py`): SQLite database with `hvac` table (time, stage, temperature, humidity); hourly JSON exports to web server
3. **Web Ingestion Layer** (`web/readJson.php`): PHP endpoint receives JSON arrays via GET parameter, validates record format, inserts into shared database

### GPIO Pin Mapping
- Stage 1 (cooling): GPIO 17
- Stage 2 (cooling): GPIO 22  
- Stage 3 (heating electric coils): GPIO 27

### Data Flow
1. Physical HVAC relay triggers GPIO pin state change
2. `HvacStage` class (Button handler) calls `setStage(stageNum)` → reads sensor → `writeHvac()` to SQLite
3. Every hour: `makeJSON()` exports records since last send, `sendJson()` transmits to PHP endpoint
4. PHP `readJson.php` receives JSON array, validates each record's type signature, inserts into web server's database

## Key Patterns & Conventions

### Temperature Unit Conversion
Always convert raw Celsius from AM2320 sensor to Fahrenheit: `(celsius * 9.0 / 5.0) + 32.0`
See: `hvac.py` line 30

### Database Design
Both Pi and web server maintain identical schema:
```sql
CREATE TABLE hvac (
  time INT PRIMARY KEY,        -- Unix timestamp
  stage INT,                   -- 0-3 (0=off, 1-3=heating/cooling stages)
  temperature REAL,            -- Fahrenheit
  humidity REAL                -- Percentage
)
```

### Record Validation Rules (PHP)
- Exactly 4 elements per record
- Element [0] (time): must be int
- Element [1] (stage): must be int
- Element [2] (temperature): must be float
- Element [3] (humidity): must be float
See: `readJson.php` lines 44-62

### Stage Transitions
Stage changes are discrete: pressing GPIO triggers `stageOn()` → increments stage, releasing triggers `stageOff()` → decrements by 1. Stage 0 = system off.

## Critical Workflows

### Local Testing
Run Pi service: `cd pi/ && python3 hvac.py`
Test data insert: `cd pi/ && python3 -c "from hvac_database import dumpDatabase; dumpDatabase()"`

### Production Deployment (Raspberry Pi)
1. Copy `hvac.service` to `/etc/systemd/system` (chmod 644)
2. Run: `sudo systemctl enable hvac.service`
3. Monitor logs: `journalctl -u hvac.service -f`

### Data Transmission
`sendJson()` in `hvac.py` is currently stubbed (returns True). Implementation must:
- URL-encode JSON array to GET parameter: `http://server/readJson.php?json=[...]`
- Handle network failures gracefully (logging failures, retries on next cycle)
- Update `lastSendTime` only on successful transmission

## Project Quirks & Important Notes

- **Time-based querying**: `makeJSON()` uses Unix timestamps; query `WHERE time > ?` to get delta records since last send
- **Persistent state file**: `lastJsonWrite.txt` stores last transmission time; used to track which records need sending
- **Hourly batching**: `sendDelay = 3600` seconds ensures data is batched, reducing network overhead and server load
- **Database auto-creation**: Both `hvac_database.py` and `readJson.php` auto-create their databases on first run if not present
- **Loose coupling**: Pi and web server databases are intentionally separate; web endpoint is the only bridge

## Files to Reference
- **Architecture**: `README.txt` (hardware wiring), `hvac.py` (main loop logic)
- **Database patterns**: `hvac_database.py` (Pi-side), `readJson.php` (server-side)
- **Deployment**: `hvac.service` (systemd unit file)
