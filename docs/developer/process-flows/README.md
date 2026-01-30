# Process Flows

Detailed process flow diagrams and explanations for understanding how JOOClient works internally.

## Core Flows

### 🔧 [Factory Creation](factory-creation.md)
How `Factory::make()` creates a configured client instance.

### 🔄 [Request Lifecycle](request-lifecycle.md)
Complete flow from `Client::request()` to response, including all middleware.

### 🧩 [Middleware Stack](middleware-stack.md)
How middleware is composed and executed in order.

## Feature Flows

### 📝 [Logging Flow](logging-flow.md)
How logging middleware captures and stores request/response data.

### 💾 [Caching Flow](caching-flow.md)
How cache middleware handles cache hits and misses.

### 🔁 [Retry Flow](retry-flow.md)
How retry middleware handles failed requests with exponential backoff.

### ⚡ [Circuit Breaker Flow](circuit-breaker-flow.md)
Circuit breaker state transitions and decision logic.

### 🔗 [Request Chaining Flow](request-chaining-flow.md)
How request chains execute sequentially with conditionals.

## Understanding the Flows

Each flow document includes:
- **Mermaid flowchart** - Visual representation of the process
- **Step-by-step explanation** - Detailed walkthrough
- **Decision points** - Key decision logic
- **Error handling** - Error paths and fallbacks
- **Code references** - Where to find the implementation

## Flow Diagram Format

All flowcharts use [Mermaid](https://mermaid.js.org/) syntax, which is supported by:
- GitHub
- GitLab
- Many documentation tools
- VS Code with Mermaid extension

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
