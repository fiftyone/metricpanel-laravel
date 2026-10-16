<?php

declare(strict_types=1);
// Install dev dependencies in a separate Composer harness and pass its autoload path.
// Database isolation is unconditional: this script creates only sqlite::memory:.
// Ignore host environment entirely; all configuration is supplied below.
function env($key, $default = null) { return $default; }
require $argv[1] ?? __DIR__.'/../vendor/autoload.php';
use Illuminate\Bus\Dispatcher;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\{SQLiteConnection, DatabaseTransactionsManager};
use Illuminate\Events\Dispatcher as Events;
use Illuminate\Queue\DatabaseQueue;
use MetricPanel\{Client, HttpResponse, Transport};
use MetricPanel\Laravel\{MetricPanel, MetricPanelServiceProvider, DeliveryResult};
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
// Laravel's global event helper belongs to the full application; the isolated harness
// routes it through the same event dispatcher without booting an application or its env.
if (!function_exists('event')) {
    function event($value) { return Container::getInstance()->make('events')->dispatch($value); }
}
$app = new Container();
Container::setInstance($app);
$app->instance('config', new Repository([
    'metricpanel' => ['token' => 'test-secret', 'api_url' => 'https://api.metricpanel.test', 'connection' => 'isolated', 'queue' => 'metricpanel'],
    'queue' => ['connections' => ['isolated' => ['driver' => 'database']], 'failed' => ['driver' => 'null']],
]));
$events = new Events($app);
$app->instance('events', $events);
$app->instance(\Illuminate\Contracts\Events\Dispatcher::class, $events);
$transactions = new DatabaseTransactionsManager();
$app->instance('db.transactions', $transactions);
$db = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['name' => 'isolated']);
$db->setTransactionManager($transactions);
$db->statement('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT, payload TEXT, attempts INTEGER, reserved_at INTEGER NULL, available_at INTEGER, created_at INTEGER)');
$queue = new DatabaseQueue($db, 'jobs', 'metricpanel', 90, false);
$queue->setContainer($app);
$queue->setConnectionName('isolated');
$bus = new Dispatcher($app, fn () => $queue);
$app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $bus);
$app->instance(Container::class, $app);
$app->instance(\Illuminate\Contracts\Container\Container::class, $app);
(new MetricPanelServiceProvider($app))->register();
$sdk = $app->make(MetricPanel::class);
$event = ['schemaVersion' => 1, 'eventId' => 'stable-event-id', 'operatorId' => 'operator', 'playerId' => 'player', 'transactionId' => 'txn', 'timestamp' => '2026-01-01T00:00:00Z', 'type' => 'deposit.completed', 'cashAmount' => '10000', 'bonusAmount' => '0', 'currency' => 'EUR', 'precision' => 2];
$db->beginTransaction();
$sdk->igaming($event);
check($queue->size() === 0, 'Job enqueued before commit');
$db->rollBack();
check($queue->size() === 0, 'Rollback published a job');
$db->beginTransaction();
$sdk->igaming($event);
check($queue->size() === 0, 'Job enqueued before commit');
$db->commit();
check($queue->size() === 1, 'Commit did not publish the job');
$payload = (string) $db->table('jobs')->value('payload');
check(!str_contains($payload, 'test-secret'), 'API token serialized into queue payload');
$transport = new class implements Transport {
    public HttpResponse $response;
    public array $payloads = [];
    public function __construct() { $this->response = new HttpResponse(429, '{"status":"error","error":{"code":"rate_limited"}}', '120'); }
    public function post(string $url, string $token, array $payload): HttpResponse { $this->payloads[] = $payload; return $this->response; }
};
$client = new Client('test-secret', 'https://api.metricpanel.test', $transport);
$job = $queue->pop();
$command = unserialize($job->payload()['data']['command']);
$command->setJob($job);
$command->handle($client);
check($job->isReleased(), '429 did not release for retry');
check($db->table('jobs')->value('available_at') >= time() + 119, 'Retry-After was ignored');
$db->table('jobs')->update(['available_at' => time()]);
$job = $queue->pop();
$retried = unserialize($job->payload()['data']['command']);
check($retried->event === $command->event, 'Retry changed payload');
$results = [];
$events->listen(DeliveryResult::class, function ($result) use (&$results) { $results[] = $result; });
$transport->response = new HttpResponse(200, '{"status":"success","data":{"created":false,"eventId":"stable-event-id"}}');
$retried->setJob($job);
$retried->handle($client);
check(count($results) === 1 && $results[0]->delivered && $results[0]->created === false, 'Duplicate acknowledgement not delivered');
$job->delete();
$sdk->igaming($event);
$job = $queue->pop();
$command = unserialize($job->payload()['data']['command']);
$transport->response = new HttpResponse(409, '{"status":"error","error":{"code":"event_conflict"}}');
$command->setJob($job);
$command->handle($client);
check($job->hasFailed(), 'Permanent conflicts should fail immediately');
check(count($results) === 2 && !$results[1]->delivered && $results[1]->errorCode === 'event_conflict', 'Failure diagnostics absent');
foreach (['sync', 'deferred', 'background', 'null', ''] as $driver) {
    try { (new MetricPanel($bus, 'sync', 'metricpanel', $driver))->igaming($event); throw new RuntimeException('Unsafe queue driver accepted'); }
    catch (LogicException) {}
}
echo "Laravel SQLite queue: commit, rollback, serialization, retry, failure and diagnostics checks passed.\n";
