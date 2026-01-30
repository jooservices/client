# Feature Flow Diagrams

This document contains ASCII flow diagrams for all implemented features in jooclient.

---

## 1. Rate Limiting Flow

### Token Bucket Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    HTTP Request Flow                             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  RateLimitingMiddleware::handle()                               │
│  - Extract domain/IP from request                              │
│  - Generate rate limit key                                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  TokenBucketStrategy::check(key)                                │
│                                                                  │
│  1. Load bucket state from cache                                │
│     ┌─────────────────────┐                                    │
│     │ tokens: 50          │                                    │
│     │ last_refill: 12345  │                                    │
│     └─────────────────────┘                                    │
│                                                                  │
│  2. Calculate elapsed time                                       │
│     elapsed = now - last_refill                                 │
│                                                                  │
│  3. Refill tokens                                               │
│     tokens_to_add = (elapsed / per_seconds) * max_requests    │
│     tokens = min(max_requests, tokens + tokens_to_add)          │
│                                                                  │
│  4. Check if tokens available                                   │
│     ┌─────────────────────────────────────┐                    │
│     │ IF tokens > 0:                      │                    │
│     │   tokens--                          │                    │
│     │   return ALLOWED                    │                    │
│     │ ELSE:                               │                    │
│     │   calculate retry_after            │                    │
│     │   return BLOCKED                    │                    │
│     └─────────────────────────────────────┘                    │
│                                                                  │
│  5. Save updated state to cache                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                    ┌─────────┴─────────┐
                    │                    │
                    ▼                    ▼
            ┌───────────────┐    ┌───────────────┐
            │   ALLOWED     │    │   BLOCKED     │
            └───────────────┘    └───────────────┘
                    │                    │
                    ▼                    ▼
            ┌───────────────┐    ┌───────────────┐
            │ Continue to   │    │ Return 429    │
            │ next handler  │    │ Response      │
            └───────────────┘    └───────────────┘
```

### Sliding Window Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│  SlidingWindowStrategy::check(key)                              │
│                                                                  │
│  1. Load request timestamps from cache                          │
│     ┌─────────────────────┐                                    │
│     │ [12340, 12341,      │                                    │
│     │  12342, 12343, ...] │                                    │
│     └─────────────────────┘                                    │
│                                                                  │
│  2. Calculate window start                                       │
│     window_start = now - per_seconds                            │
│                                                                  │
│  3. Filter old requests                                         │
│     requests = filter(requests, timestamp > window_start)       │
│                                                                  │
│  4. Check count                                                 │
│     ┌─────────────────────────────────────┐                    │
│     │ IF count(requests) < max_requests: │                    │
│     │   add current timestamp            │                    │
│     │   save to cache                    │                    │
│     │   return ALLOWED                   │                    │
│     │ ELSE:                              │                    │
│     │   oldest = min(requests)          │                    │
│     │   retry_after = oldest + window - now                   │
│     │   return BLOCKED                   │                    │
│     └─────────────────────────────────────┘                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Async/Concurrent Request Flow

### Single Async Request

```
┌─────────────────────────────────────────────────────────────────┐
│  Client::getAsync('https://api.example.com/users')              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Client::requestAsync('GET', uri, options)                      │
│                                                                  │
│  1. Call Guzzle's requestAsync()                                │
│     promise = client->requestAsync('GET', uri, options)        │
│                                                                  │
│  2. Transform promise result                                    │
│     promise->then(fn($response) => new ResponseWrapper($response))│
│                                                                  │
│  3. Return PromiseInterface                                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  Promise         │
                    │  (not resolved)  │
                    └─────────────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ▼                   ▼
            ┌───────────────┐   ┌───────────────┐
            │  Wait for     │   │  Use in       │
            │  promise->wait()│   │  batch        │
            └───────────────┘   └───────────────┘
```

### Batch Processing with settle()

```
┌─────────────────────────────────────────────────────────────────┐
│  Client::settle($promises)                                      │
│                                                                  │
│  Input:                                                          │
│  ┌─────────────────────────────────────┐                     │
│  │ [                                     │                     │
│  │   'users' => Promise<UserResponse>,   │                     │
│  │   'posts' => Promise<PostResponse>,    │                     │
│  │   'comments' => Promise<CommentResponse>│                  │
│  │ ]                                     │                     │
│  └─────────────────────────────────────┘                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  GuzzleHttp\Promise\Utils::settle($promises)                   │
│                                                                  │
│  1. Wait for all promises to settle                             │
│     ┌─────────────────────────────────────┐                    │
│     │ FOR EACH promise:                    │                    │
│     │   wait for fulfillment/rejection    │                    │
│     │   record state and value/reason     │                    │
│     └─────────────────────────────────────┘                    │
│                                                                  │
│  2. Return settled results                                       │
│     ┌─────────────────────────────────────┐                    │
│     │ [                                     │                    │
│     │   'users' => [                        │                    │
│     │     'state' => 'fulfilled',           │                    │
│     │     'value' => ResponseWrapper       │                    │
│     │   ],                                  │                    │
│     │   'posts' => [                        │                    │
│     │     'state' => 'rejected',           │                    │
│     │     'reason' => Exception            │                    │
│     │   ]                                   │                    │
│     │ ]                                     │                    │
│     └─────────────────────────────────────┘                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Transform results                                               │
│                                                                  │
│  ┌─────────────────────────────────────┐                       │
│  │ [                                     │                       │
│  │   'users' => ResponseWrapper,        │                       │
│  │   'posts' => Exception,               │                       │
│  │   'comments' => ResponseWrapper       │                       │
│  │ ]                                     │                       │
│  └─────────────────────────────────────┘                       │
└─────────────────────────────────────────────────────────────────┘
```

### Concurrency-Limited Pool

```
┌─────────────────────────────────────────────────────────────────┐
│  Client::pool($promises, ['concurrency' => 5])                 │
│                                                                  │
│  Input: 100 promises                                            │
│  Concurrency: 5                                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Process in batches                                             │
│                                                                  │
│  Batch 1: [P1, P2, P3, P4, P5]                                 │
│    ┌─────────────────────────────────────┐                    │
│    │ Wait for all 5 to complete          │                    │
│    └─────────────────────────────────────┘                    │
│                                                                  │
│  Batch 2: [P6, P7, P8, P9, P10]                                │
│    ┌─────────────────────────────────────┐                    │
│    │ Wait for all 5 to complete          │                    │
│    └─────────────────────────────────────┘                    │
│                                                                  │
│  ... (20 batches total)                                         │
│                                                                  │
│  Batch 20: [P96, P97, P98, P99, P100]                          │
│    ┌─────────────────────────────────────┐                    │
│    │ Wait for all 5 to complete          │                    │
│    └─────────────────────────────────────┘                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  All Results     │
                    │  (100 items)     │
                    └─────────────────┘
```

---

## 3. Circuit Breaker Flow

### State Machine

```
                    ┌──────────────┐
                    │   CLOSED     │
                    │  (Normal)    │
                    └──────────────┘
                           │
                           │ failures >= threshold
                           ▼
                    ┌──────────────┐
                    │    OPEN      │
                    │  (Blocking)  │
                    └──────────────┘
                           │
                           │ timeout elapsed
                           ▼
                    ┌──────────────┐
                    │  HALF_OPEN   │
                    │  (Testing)   │
                    └──────────────┘
                           │
        ┌──────────────────┴──────────────────┐
        │                                    │
        │ success >= threshold              │ failure
        ▼                                    ▼
┌──────────────┐                    ┌──────────────┐
│   CLOSED     │                    │    OPEN      │
│  (Normal)    │                    │  (Blocking)  │
└──────────────┘                    └──────────────┘
```

### Request Flow Through Circuit Breaker

```
┌─────────────────────────────────────────────────────────────────┐
│  HTTP Request                                                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  CircuitBreakerMiddleware::handle()                             │
│                                                                  │
│  1. Generate circuit breaker key                                │
│     key = 'circuit_breaker:' + domain                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  CircuitBreakerFactory::getOrCreate(key)                        │
│                                                                  │
│  - Load state from cache                                        │
│  - Create new CircuitBreaker if not exists                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  CircuitBreaker::isAllowed()                                     │
│                                                                  │
│  1. Update state based on timeout                               │
│     ┌─────────────────────────────────────┐                   │
│     │ IF state == OPEN:                    │                   │
│     │   elapsed = now - opened_at           │                   │
│     │   IF elapsed >= timeout:             │                   │
│     │     state = HALF_OPEN                 │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  2. Check if allowed                                            │
│     ┌─────────────────────────────────────┐                   │
│     │ IF state == OPEN:                   │                   │
│     │   return false                      │                   │
│     │ ELSE:                               │                   │
│     │   return true                       │                   │
│     └─────────────────────────────────────┘                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ▼                   ▼
            ┌───────────────┐   ┌───────────────┐
            │   ALLOWED     │   │   BLOCKED     │
            └───────────────┘   └───────────────┘
                    │                   │
                    ▼                   ▼
            ┌───────────────┐   ┌───────────────┐
            │ Make Request  │   │ Return 503    │
            └───────────────┘   └───────────────┘
                    │
                    ▼
        ┌───────────────────────────┐
        │  Record Result            │
        │                           │
        │  IF success:              │
        │    recordSuccess()        │
        │    IF HALF_OPEN:          │
        │      success_count++      │
        │      IF success_count >= threshold:│
        │        state = CLOSED     │
        │                           │
        │  IF failure:              │
        │    recordFailure()        │
        │    IF HALF_OPEN:          │
        │      state = OPEN         │
        │    ELSE:                  │
        │      failure_count++      │
        │      IF failure_count >= threshold:│
        │        state = OPEN       │
        └───────────────────────────┘
```

---

## 4. Request/Response Interceptor Flow

### Request Flow with Interceptors

```
┌─────────────────────────────────────────────────────────────────┐
│  HTTP Request                                                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  InterceptorMiddleware::__invoke()                               │
│                                                                  │
│  1. Apply Request Interceptors                                  │
│     ┌─────────────────────────────────────┐                   │
│     │ FOR EACH request interceptor:        │                   │
│     │   [request, options] =               │                   │
│     │     interceptor(request, options)     │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│     Example:                                                    │
│     - Add custom headers                                        │
│     - Modify request body                                       │
│     - Add authentication tokens                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Call Next Handler                                              │
│  handler(request, options)                                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  Response       │
                    │  (or Exception) │
                    └─────────────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ▼                   ▼
        ┌───────────────────┐  ┌───────────────────┐
        │   Success         │  │   Error           │
        └───────────────────┘  └───────────────────┘
                    │                   │
                    ▼                   ▼
┌─────────────────────────────────────────────────────────────────┐
│  Apply Response Interceptors                                     │
│  ┌─────────────────────────────────────┐                       │
│  │ FOR EACH response interceptor:       │                       │
│  │   response = interceptor(            │                       │
│  │     response, request                │                       │
│  │   )                                   │                       │
│  └─────────────────────────────────────┘                       │
│                                                                  │
│  Example:                                                       │
│  - Transform response body                                       │
│  - Validate response                                            │
│  - Refresh tokens on 401                                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Apply Error Interceptors                                       │
│  ┌─────────────────────────────────────┐                       │
│  │ FOR EACH error interceptor:          │                       │
│  │   interceptor(exception, request)    │                       │
│  └─────────────────────────────────────┘                       │
│                                                                  │
│  Example:                                                       │
│  - Log errors                                                   │
│  - Send to error tracking service                              │
│  - Retry with exponential backoff                             │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. Request Template Flow

### Template Registration

```
┌─────────────────────────────────────────────────────────────────┐
│  Factory::registerTemplate('github_api', [                      │
│    'base_uri' => 'https://api.github.com',                      │
│    'headers' => ['Accept' => 'application/vnd.github.v3+json'],│
│    'timeout' => 30                                               │
│  ])                                                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  TemplateManager::register(name, options)                       │
│                                                                  │
│  1. Create RequestTemplate                                      │
│     template = new RequestTemplate(name, options)               │
│                                                                  │
│  2. Store in templates array                                    │
│     templates[name] = template                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Template Usage

```
┌─────────────────────────────────────────────────────────────────┐
│  Client::get('/repos/owner/repo', [                              │
│    'template' => 'github_api',                                    │
│    'headers' => ['X-Custom' => 'value']                          │
│  ])                                                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Client::request('GET', uri, options)                            │
│                                                                  │
│  1. Check if template specified                                  │
│     IF options['template'] exists:                              │
│       template_name = options['template']                       │
│                                                                  │
│  2. Get template from manager                                   │
│     template = factory->getTemplateManager()->get(template_name)│
│                                                                  │
│  3. Merge template options with request options                  │
│     ┌─────────────────────────────────────┐                    │
│     │ merged = template->mergeOptions(     │                    │
│     │   request_options                    │                    │
│     │ )                                     │                    │
│     │                                       │                    │
│     │ Template:                            │                    │
│     │   base_uri: 'https://api.github.com' │                    │
│     │   headers: {Accept: '...'}           │                    │
│     │   timeout: 30                        │                    │
│     │                                       │                    │
│     │ Request:                              │                    │
│     │   headers: {X-Custom: 'value'}       │                    │
│     │                                       │                    │
│     │ Merged:                               │                    │
│     │   base_uri: 'https://api.github.com' │                    │
│     │   headers: {                         │                    │
│     │     Accept: '...',                   │                    │
│     │     X-Custom: 'value'                 │                    │
│     │   }                                   │                    │
│     │   timeout: 30                        │                    │
│     └─────────────────────────────────────┘                    │
│                                                                  │
│  4. Remove template key                                         │
│     unset(options['template'])                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Make Request with Merged Options                               │
│  client->request('GET', uri, merged_options)                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 6. Correlation ID Flow

### Request Flow

```
┌─────────────────────────────────────────────────────────────────┐
│  HTTP Request                                                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  CorrelationIdMiddleware::__invoke()                           │
│                                                                  │
│  1. Check if correlation ID already exists                      │
│     ┌─────────────────────────────────────┐                    │
│     │ IF request->hasHeader(headerName): │                    │
│     │   skip (use existing)               │                    │
│     └─────────────────────────────────────┘                    │
│                                                                  │
│  2. Generate or use provided correlation ID                    │
│     ┌─────────────────────────────────────┐                    │
│     │ IF options['correlation_id']:       │                    │
│     │   id = options['correlation_id']    │                    │
│     │ ELSE:                               │                    │
│     │   id = generateCorrelationId()      │                    │
│     │     - Use custom generator if set   │                    │
│     │     - Or generate random hex        │                    │
│     └─────────────────────────────────────┘                    │
│                                                                  │
│  3. Add to request header                                       │
│     request = request->withHeader(headerName, id)               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Make Request                                                    │
│  handler(request, options)                                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  Response       │
                    └─────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Add Correlation ID to Response                                 │
│                                                                  │
│  response = response->withHeader(headerName, correlationId)     │
│                                                                  │
│  Result:                                                         │
│  - Request header: X-Correlation-ID: abc123...                 │
│  - Response header: X-Correlation-ID: abc123...                │
└─────────────────────────────────────────────────────────────────┘
```

---

## 7. Complete Middleware Stack Flow

### Request Processing Order

```
┌─────────────────────────────────────────────────────────────────┐
│                    HTTP Request                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  HandlerStack (Guzzle)                                          │
│                                                                  │
│  Order of execution (bottom to top):                            │
│                                                                  │
│  1. DesktopUserAgentMiddleware (unshift)                       │
│     ┌─────────────────────────────────────┐                   │
│     │ - Generate/retrieve user agent       │                   │
│     │ - Add to request header              │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  2. InterceptorMiddleware (if enabled)                           │
│     ┌─────────────────────────────────────┐                   │
│     │ - Apply request interceptors        │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  3. CorrelationIdMiddleware (if enabled)                        │
│     ┌─────────────────────────────────────┐                   │
│     │ - Generate correlation ID           │                   │
│     │ - Add to request header             │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  4. RateLimitingMiddleware (if enabled)                         │
│     ┌─────────────────────────────────────┐                   │
│     │ - Check rate limit                   │                   │
│     │ - Block if exceeded                  │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  5. CircuitBreakerMiddleware (if enabled)                       │
│     ┌─────────────────────────────────────┐                   │
│     │ - Check circuit state               │                   │
│     │ - Block if open                      │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  6. CacheMiddleware (if enabled)                                │
│     ┌─────────────────────────────────────┐                   │
│     │ - Check cache                        │                   │
│     │ - Return cached if found             │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  7. RetryMiddleware (if enabled)                                │
│     ┌─────────────────────────────────────┐                   │
│     │ - Retry on failure                   │                   │
│     │ - Exponential backoff                │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  8. LoggingMiddleware (if enabled)                              │
│     ┌─────────────────────────────────────┐                   │
│     │ - Log request/response              │                   │
│     │ - Sanitize sensitive data           │                   │
│     └─────────────────────────────────────┘                   │
│                                                                  │
│  9. HTTP Handler (actual request)                               │
│     ┌─────────────────────────────────────┐                   │
│     │ - Send HTTP request                  │                   │
│     │ - Receive HTTP response              │                   │
│     └─────────────────────────────────────┘                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  HTTP Response  │
                    └─────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Response Processing (reverse order)                            │
│                                                                  │
│  9. LoggingMiddleware                                           │
│     - Log response                                              │
│                                                                  │
│  8. RetryMiddleware                                             │
│     - Check if retry needed                                     │
│                                                                  │
│  7. CacheMiddleware                                             │
│     - Cache successful responses                                │
│                                                                  │
│  6. CircuitBreakerMiddleware                                    │
│     - Record success/failure                                    │
│     - Update circuit state                                      │
│                                                                  │
│  5. RateLimitingMiddleware                                      │
│     - Add rate limit headers                                    │
│                                                                  │
│  4. CorrelationIdMiddleware                                     │
│     - Add correlation ID to response                           │
│                                                                  │
│  3. InterceptorMiddleware                                       │
│     - Apply response interceptors                              │
│                                                                  │
│  2. DesktopUserAgentMiddleware                                  │
│     - Cleanup user agent session                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  ResponseWrapper│
                    │  (to caller)    │
                    └─────────────────┘
```

---

## 8. Factory Creation Flow

```
┌─────────────────────────────────────────────────────────────────┐
│  new Factory()                                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Factory::__construct()                                         │
│                                                                  │
│  - Initialize options = []                                      │
│  - Initialize middlewares = []                                  │
│  - Initialize templateManager = new TemplateManager()          │
│  - Initialize interceptorMiddleware = null                      │
│  - Initialize correlationIdEnabled = false                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Fluent Configuration                                           │
│                                                                  │
│  factory                                                         │
│    ->enableLogging()                                            │
│    ->enableCache()                                              │
│    ->enableRateLimiting()                                       │
│    ->enableCircuitBreaker()                                     │
│    ->enableCorrelationIds()                                     │
│    ->onRequest(fn)                                              │
│    ->onResponse(fn)                                             │
│    ->registerTemplate('api', [...])                             │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Factory::make()                                                │
│                                                                  │
│  1. Create HandlerStack                                         │
│  2. Add middlewares in order                                   │
│  3. Create GuzzleClient                                         │
│  4. Return Client wrapper                                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  Client          │
                    │  (ready to use)  │
                    └─────────────────┘
```

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

