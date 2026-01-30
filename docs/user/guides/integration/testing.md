# Testing Guide - Data Inspection

## How to Inspect Test Data

The tests create data in MySQL and MongoDB. Here's how to inspect it:

### MySQL Data Inspection

The tests use database: `jooclient`
Table: `client_request_logs`

#### Using MySQL CLI:
```bash
mysql -h127.0.0.1 -uroot -proot jooclient -e "SELECT * FROM client_request_logs ORDER BY id DESC LIMIT 10\G"
```

#### Using TablePlus / Sequel Pro:
- Host: 127.0.0.1
- Port: 3306
- Database: jooclient
- Username: root
- Password: root
- Table: client_request_logs

#### Using PHP (from project):
```php
<?php
require 'vendor/autoload.php';

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=jooclient', 'root', 'root');
$stmt = $pdo->query('SELECT * FROM client_request_logs ORDER BY id DESC LIMIT 10');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo "Method: {$row['method']}, Path: {$row['path']}, Status: {$row['response_status']}\n";
    echo "Message: {$row['message']}\n";
    echo "---\n";
}
```

#### Check total count:
```sql
SELECT COUNT(*) as total FROM client_request_logs;
```

#### Check by log level:
```sql
SELECT level, COUNT(*) as count
FROM client_request_logs
GROUP BY level;
```

#### Check errors:
```sql
SELECT method, path, response_status, LEFT(message, 100) as message
FROM client_request_logs
WHERE level = 'error'
ORDER BY id DESC
LIMIT 10;
```

---

### MongoDB Data Inspection

The tests use database: `jooclient_test`
Collection: `client_request_logs`

#### Using MongoDB Shell (mongosh):
```bash
mongosh jooclient_test --eval "db.client_request_logs.countDocuments()"
mongosh jooclient_test --eval "db.client_request_logs.find().sort({created_at: -1}).limit(5).pretty()"
```

#### Using MongoDB Compass:
- Connection String: mongodb://127.0.0.1:27017
- Database: jooclient_test
- Collection: client_request_logs

#### Using PHP (from project):
```php
<?php
require 'vendor/autoload.php';

$client = new MongoDB\Client('mongodb://127.0.0.1:27017');
$collection = $client->selectDatabase('jooclient_test')
                    ->selectCollection('client_request_logs');

$documents = $collection->find([], ['limit' => 10, 'sort' => ['created_at' => -1]]);

foreach ($documents as $doc) {
    echo "Method: {$doc['method']}, Path: {$doc['path']}, Status: {$doc['response_status']}\n";
    echo "Message: {$doc['message']}\n";
    echo "---\n";
}
```

#### Count documents:
```javascript
db.client_request_logs.countDocuments()
```

#### Find errors:
```javascript
db.client_request_logs.find({level: "error"}).sort({created_at: -1}).limit(5)
```

#### Aggregate by status:
```javascript
db.client_request_logs.aggregate([
  {$group: {_id: "$response_status", count: {$sum: 1}}},
  {$sort: {count: -1}}
])
```

---

### Monolog File Logs

Monolog writes to temporary directory.

#### Find log files:
```bash
ls -la /tmp/jooclient*.log
find /tmp -name "*jooclient*" -type f
```

#### View logs:
```bash
tail -100 /tmp/jooclient.log
```

#### JSON formatted logs:
```bash
tail -20 /tmp/jooclient.log | jq '.'
```

---

## Database Connection Details

### MySQL (for tests):
- **Host**: 127.0.0.1
- **Port**: 3306
- **Database**: `jooclient`
- **Username**: root
- **Password**: root
- **Table**: `client_request_logs`

### MongoDB (for tests):
- **DSN**: mongodb://127.0.0.1:27017
- **Database**: `jooclient_test`
- **Collection**: `client_request_logs`

---

## Sample Queries

### MySQL: Get All Test Data

```sql
-- Total logs
SELECT COUNT(*) FROM client_request_logs;

-- By method
SELECT method, COUNT(*) as count
FROM client_request_logs
GROUP BY method;

-- By status code
SELECT response_status, COUNT(*) as count
FROM client_request_logs
WHERE response_status IS NOT NULL
GROUP BY response_status
ORDER BY response_status;

-- Recent errors
SELECT
    id,
    method,
    path,
    response_status,
    level,
    message,
    created_at
FROM client_request_logs
WHERE level = 'error'
ORDER BY created_at DESC
LIMIT 10;

-- Full details of one request
SELECT * FROM client_request_logs WHERE id = 1 \G
```

### MongoDB: Get All Test Data

```javascript
// Total documents
db.client_request_logs.countDocuments()

// By method
db.client_request_logs.aggregate([
  {$group: {_id: "$method", count: {$sum: 1}}}
])

// By status
db.client_request_logs.aggregate([
  {$group: {_id: "$response_status", count: {$sum: 1}}},
  {$sort: {_id: 1}}
])

// Recent errors
db.client_request_logs.find({level: "error"})
  .sort({created_at: -1})
  .limit(10)
  .pretty()

// All MongoDB test data
db.client_request_logs.find().pretty()
```

---

## Expected Test Data

After running the full test suite, you should find:

### MySQL Database:
- Multiple log entries from various test scenarios
- Different HTTP methods (GET, POST)
- Various response statuses (200, 201, 400, 404, 500, etc.)
- Error logs with full context
- Request/response bodies captured

### MongoDB Database:
- 3+ documents from MongoDB-specific tests
- Documents with MongoDB-native types (UTCDateTime)
- Request/response data in flexible schema
- Error documents with exception details

### Monolog Files:
- JSON-formatted log entries (if formatter was 'json')
- Plain text entries otherwise
- Located in /tmp/jooclient.log

---

## Data Structure

### MySQL Table Structure:
```
client_request_logs
├── id (auto increment)
├── method (GET, POST, etc.)
├── path (/api/users)
├── request_endpoint (full URL)
├── request_headers (JSON)
├── request_body (text)
├── response_status (200, 404, etc.)
├── response_body (text)
├── level (info, error, debug)
├── message (log message)
├── context (JSON)
├── created_at (timestamp)
└── updated_at (timestamp)
```

### MongoDB Document Structure:
```javascript
{
  "_id": ObjectId("..."),
  "method": "GET",
  "path": "/mongodb-test",
  "request_endpoint": "/mongodb-test",
  "request_headers": "{...}",
  "request_body": null,
  "response_status": 200,
  "response_body": "OK",
  "level": "info",
  "message": "Request logged",
  "context": {...},
  "created_at": ISODate("2025-11-06T..."),
  "updated_at": ISODate("2025-11-06T...")
}
```

---

## Verify Test Results

### Check MySQL via PHP:

Create file `check_mysql.php`:
```php
<?php
require 'vendor/autoload.php';

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=jooclient', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== MySQL Client Request Logs ===\n\n";

    $count = $pdo->query('SELECT COUNT(*) FROM client_request_logs')->fetchColumn();
    echo "Total logs: $count\n\n";

    $stmt = $pdo->query('
        SELECT id, method, path, response_status, level,
               LEFT(message, 60) as message_preview,
               created_at
        FROM client_request_logs
        ORDER BY id DESC
        LIMIT 10
    ');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']} | {$row['method']} {$row['path']} | Status: {$row['response_status']} | Level: {$row['level']}\n";
        echo "Message: {$row['message_preview']}\n";
        echo "Time: {$row['created_at']}\n";
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

Run: `php check_mysql.php`

### Check MongoDB via PHP:

Create file `check_mongodb.php`:
```php
<?php
require 'vendor/autoload.php';

try {
    $client = new MongoDB\Client('mongodb://127.0.0.1:27017');
    $collection = $client->selectDatabase('jooclient_test')
                        ->selectCollection('client_request_logs');

    echo "=== MongoDB Client Request Logs ===\n\n";

    $count = $collection->countDocuments();
    echo "Total documents: $count\n\n";

    $cursor = $collection->find(
        [],
        [
            'limit' => 10,
            'sort' => ['created_at' => -1]
        ]
    );

    foreach ($cursor as $document) {
        echo "ID: {$document['_id']} | {$document['method']} {$document['path']} | Status: {$document['response_status']} | Level: {$document['level']}\n";
        echo "Message: {$document['message']}\n";
        echo "Time: " . $document['created_at']->toDateTime()->format('Y-m-d H:i:s') . "\n";
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

Run: `php check_mongodb.php`

---

## Test Summary

✅ **All 22 tests passed**
✅ **104 assertions successful**
✅ **0 errors, 0 failures**

### Tests Run:
1. ✅ MySQL logging tests (6)
2. ✅ MongoDB logging tests (3)
3. ✅ Monolog logging tests (2)
4. ✅ Guzzle exception tests (8)
5. ✅ Other tests (3)

### Data Created:
- **MySQL**: Logs in `jooclient.client_request_logs` table
- **MongoDB**: Documents in `jooclient_test.client_request_logs` collection
- **Monolog**: Files in `/tmp/` directory

**Note**: Some tests clean up after themselves (tearDown). Data from tests without tearDown will persist for inspection.


