# Circuit Breaker Flow

Circuit breaker state transitions and decision logic.

## Overview

Circuit breaker protects against cascading failures by opening the circuit when failure threshold is reached, preventing requests from reaching a failing service.

## State Machine

```mermaid
stateDiagram-v2
    [*] --> Closed: Initial State
    
    Closed --> Open: Failure Threshold Reached
    Closed --> Closed: Success
    
    Open --> HalfOpen: Timeout Expired
    Open --> Open: Timeout Not Expired
    
    HalfOpen --> Closed: Success Threshold Reached
    HalfOpen --> Open: Failure Detected
    
    note right of Closed
        Normal operation
        Requests allowed
        Track failures
    end note
    
    note right of Open
        Circuit open
        Requests blocked
        Return 503
        Wait for timeout
    end note
    
    note right of HalfOpen
        Testing state
        Limited requests
        Monitor results
    end note
```

## Request Handling Flow

```mermaid
flowchart TD
    Start([Request Received]) --> GetState[Get Circuit Breaker State]
    GetState --> CheckState{Current State?}
    
    CheckState -->|Closed| AllowRequest[Allow Request]
    CheckState -->|Open| CheckTimeout{Timeout Expired?}
    CheckState -->|HalfOpen| CheckLimit{Request Limit OK?}
    
    CheckTimeout -->|No| BlockRequest[Block Request - Return 503]
    CheckTimeout -->|Yes| TransitionHalfOpen[Transition to Half-Open]
    TransitionHalfOpen --> AllowRequest
    
    CheckLimit -->|No| BlockRequest
    CheckLimit -->|Yes| AllowRequest
    
    AllowRequest --> ExecuteRequest[Execute HTTP Request]
    ExecuteRequest --> CheckResult{Request Result?}
    
    CheckResult -->|Success| RecordSuccess[Record Success]
    CheckResult -->|Failure| RecordFailure[Record Failure]
    
    RecordSuccess --> CheckHalfOpen{State is Half-Open?}
    CheckHalfOpen -->|Yes| CheckSuccessThreshold{Success Threshold Reached?}
    CheckHalfOpen -->|No| End
    CheckSuccessThreshold -->|Yes| TransitionClosed[Transition to Closed]
    CheckSuccessThreshold -->|No| End
    TransitionClosed --> End
    
    RecordFailure --> CheckFailureCount{Failure Count >= Threshold?}
    CheckFailureCount -->|Yes| TransitionOpen[Transition to Open]
    CheckFailureCount -->|No| End
    TransitionOpen --> End
    
    BlockRequest --> End([End])
    
    style Start fill:#e1f5ff
    style End fill:#c8e6c9
    style CheckState fill:#fff9c4
    style BlockRequest fill:#ffcdd2
    style AllowRequest fill:#c8e6c9
```

## States

### Closed (Normal Operation)

**Behavior:**
- All requests are allowed
- Failures are tracked
- Successes reset failure count

**Transition to Open:**
- Failure count >= threshold
- Timeout period starts

### Open (Circuit Open)

**Behavior:**
- All requests are blocked
- Returns 503 immediately
- No requests reach the service

**Transition to Half-Open:**
- Timeout period expires
- Allows limited requests to test service

### Half-Open (Testing)

**Behavior:**
- Limited requests allowed (default: 3)
- Monitors success/failure rate
- Tests if service recovered

**Transition to Closed:**
- Success threshold reached
- Service appears healthy

**Transition to Open:**
- Failure detected
- Service still failing

## Configuration

```php
$factory = (new Factory())
    ->enableCircuitBreaker([
        'failure_threshold' => 5,      // Open after 5 failures
        'timeout' => 60,               // Stay open for 60 seconds
        'half_open_max' => 3,          // Allow 3 requests in half-open
        'per_domain' => true,          // Separate circuit per domain
    ]);
```

## Code References

- **Circuit Breaker:** `src/CircuitBreaker/CircuitBreaker.php`
- **Middleware:** `src/CircuitBreaker/Middleware/CircuitBreakerMiddleware.php`
- **Factory:** `src/CircuitBreaker/CircuitBreakerFactory.php`

## Related Flows

- [Request Lifecycle](request-lifecycle.md) - Where circuit breaker fits in the request flow
- [Factory Creation](factory-creation.md) - How circuit breaker is enabled

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
