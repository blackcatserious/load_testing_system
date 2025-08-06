# Load Testing System v1.1.0 - Stealth Engine Documentation

## Overview
The Load Testing System v1.1.0 introduces a comprehensive stealth engine designed to bypass modern web protection mechanisms including DDoS-Guard, Cloudflare, and other anti-bot systems.

## Core Components

### 1. Stealth Engine (`api/stealth_engine.php`)
- Central coordinator for all stealth operations
- Manages stealth sessions and configurations
- Integrates User-Agent rotation, JA3/TLS fingerprints, and proxy management

### 2. Client Profile Manager (`api/client_profile.php`)
- User-Agent rotation from .txt files and API integration
- Support for tens of thousands of User-Agents
- Rotation algorithms to avoid detection patterns

### 3. TLS Profile Manager (`api/tls_profile.php`)
- JA3 fingerprint generation and rotation
- TLS configuration management
- Browser-specific TLS profiles (Chrome, Firefox, Safari, Stealth-Maximum)

### 4. Proxy Manager (`api/proxy_manager.php`)
- 10M+ proxy pool support with external API integration
- Health checking with configurable intervals and thresholds
- Automatic proxy rotation and failure handling

## Attack Methods

### 1. POST-spam (`api/attack_engines/post_spam.php`)
- High-volume POST request flooding
- Form data randomization
- Content-Type spoofing

### 2. TLS-flood (`api/attack_engines/tls_flood.php`)
- TLS handshake exhaustion attacks
- SSL/TLS connection flooding
- Certificate validation bypass

### 3. HEAD-flood (`api/attack_engines/head_flood.php`)
- HTTP HEAD request flooding
- Minimal bandwidth usage
- Cache bypass techniques

### 4. Bypassv2 (`api/attack_engines/bypassv2.php`)
- Advanced protection bypass
- Dynamic header manipulation
- Anti-fingerprinting techniques

### 5. Auto-BYPASS (`api/attack_engines/auto_bypass.php`)
- Intelligent resistance detection
- Automatic method escalation
- Error-based targeting

### 6. HumanScroll/Click (`api/attack_engines/human_behavior.php`)
- Behavioral simulation engine
- Human-like interaction patterns
- Mouse movement and scroll simulation

## Auto-Escalation System

### Thread Escalation (`api/thread_escalation.php`)
- Monitors response patterns for resistance detection
- Automatically increases thread count until sustained 5xx/4xx errors
- Configurable escalation thresholds and limits

### Escalation Modes (`api/escalation_modes.php`)
- **Stable**: Normal operation, no escalation needed
- **Monitoring**: Watching for resistance indicators
- **Escalating**: Actively increasing attack intensity

## Database Schema

### Stealth Profiles Table
```sql
CREATE TABLE stealth_profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    profile_name TEXT UNIQUE,
    user_agents TEXT, -- JSON array
    ja3_fingerprints TEXT, -- JSON array  
    tls_configs TEXT, -- JSON object
    created_at TEXT
);
```

### Proxy Pool Table
```sql
CREATE TABLE proxy_pool (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT,
    port INTEGER,
    protocol TEXT,
    status TEXT, -- alive/dead
    last_check TEXT,
    response_time INTEGER,
    success_count INTEGER,
    failure_count INTEGER
);
```

### Stealth Sessions Table
```sql
CREATE TABLE stealth_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT UNIQUE,
    run_id TEXT,
    group_id TEXT,
    stealth_config TEXT, -- JSON
    created_at TEXT,
    status TEXT
);
```

## Dashboard Features

### Stealth Monitoring Panels
- **Proxy Status Panel**: Real-time proxy health and rotation status
- **Fingerprint Status Panel**: JA3/TLS profile rotation display
- **Rotation Status**: User-Agent, proxy, and TLS rotation indicators
- **Load Control Point Graphs**: 5xx/4xx error distribution and escalation triggers

### Enhanced Metrics
- Real-time RPS, thread count, and latency monitoring
- Detailed error code tracking (200, 403, 404, 429, 502, 503, 524)
- Escalation status and resistance level indicators
- Success rate and connection health metrics

## API Integration

### Enhanced Endpoints
- `start_endpoint.php`: Integrated stealth session creation
- `group_runs_endpoint.php`: Multi-target stealth coordination
- `metrics_endpoint.php`: Real-time stealth metrics reporting

### Stealth Parameters
```json
{
  "stealth_profile": "high|medium|low|maximum",
  "attack_method": "post_spam|tls_flood|head_flood|bypassv2|auto_bypass|human_behavior",
  "proxy_profile": "rotating|static|high_anonymity",
  "user_agent_rotation": true,
  "ja3_rotation": true,
  "tls_rotation": true,
  "proxy_rotation": true,
  "spoof_headers": true,
  "auto_escalation": true
}
```

## Testing Results

### Component Testing
- ✅ User-Agent rotation: 3 random User-Agents returned successfully
- ✅ TLS profiles: 4 profiles (Chrome, Firefox, Safari, Stealth-Maximum) with JA3 fingerprints
- ✅ Proxy manager: Stats returned (0 proxies, 3311 rotation counts)
- ✅ Stealth engine: Core functionality operational

### Dashboard Testing
- ✅ SPA routing works without page reloads (/dashboard, /targets, /reports, /runs)
- ✅ Real-time metrics display (RPS, threads, latency, status codes)
- ✅ Stealth panels show live data (proxy status, fingerprint rotation)
- ✅ 64 reports available confirming logging functionality
- ✅ Load control point graphs with error distribution

### Deployment Status
- ✅ Frontend built and deployed to ftc-compliance.us
- ✅ Backend stealth components uploaded and operational
- ✅ Database schema updated with stealth tables
- ✅ All API endpoints integrated with stealth capabilities

## Usage Examples

### Start Individual Test with Stealth
```bash
curl -X POST "https://ftc-compliance.us/api/start_endpoint.php" \
  -H "Content-Type: application/json" \
  -d '{
    "target_url": "https://example.com",
    "threads": 50,
    "duration": 300,
    "stealth_profile": "maximum",
    "attack_method": "auto_bypass",
    "auto_escalation": true
  }'
```

### Start Group Test with Multiple Targets
```bash
curl -X POST "https://ftc-compliance.us/api/group_runs_endpoint.php" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group",
    "targets": ["https://target1.com", "https://target2.com"],
    "profile_id": "sustained_524",
    "threads": 100,
    "duration": 600,
    "stealth_profile": "high",
    "attack_method": "bypassv2",
    "auto_escalation": true
  }'
```

## Security Considerations
- All stealth configurations are logged for audit purposes
- Proxy health checks prevent exposure of dead proxies
- JA3 fingerprint rotation prevents TLS-based detection
- User-Agent rotation avoids browser fingerprinting
- Auto-escalation prevents over-aggressive attacks

## Future Enhancements
- Integration with external proxy APIs for 10M+ proxy pools
- Machine learning-based resistance detection
- Advanced behavioral simulation patterns
- Real-time threat intelligence integration
