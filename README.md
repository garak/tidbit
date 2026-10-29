# Tidbit

Short, time-sorted, 64-bit identifiers for PHP.

```
8w7E2QwnGMb   <->   3417520156305973838
```

- Fits a signed `BIGINT`: 8 bytes in the database, native indexes, no binary columns.
- 11-character Base58 string for URLs and APIs. Fixed length, so string order matches creation order.
- No coordination needed between processes (PHP-FPM workers, CLI, consumers).

Inspired by [TSID](https://github.com/f4b6a3/tsid-creator), but not compatible with it: different epoch, bit layout and encoding.

## Why

| Format | Bits | String length | DB storage |
|---|---|---|---|
| UUIDv7 | 128 | 36 (hex), 22 (Base58) | 16 bytes binary |
| ULID | 128 | 26 | 16 bytes binary |
| **Tidbit** | 63 | **11** | **8 bytes `BIGINT`** |

The primary key is copied into every secondary index and foreign key, so halving its size adds up fast.

## Installation

```bash
composer require garak/tidbit
```

Requires PHP 8.4+ on a 64-bit build.

## Usage

```php
use Garak\Tidbit\Tidbit;
use Garak\Tidbit\TidbitFactory;

$factory = new TidbitFactory();
$id = $factory->create();

$id->toInt();          // 3417520156305973838, store this
$id->toString();       // '8w7E2QwnGMb', expose this
$id->getDateTime();    // 2026-10-27T13:24:59.717+00:00
json_encode($id);      // '"8w7E2QwnGMb"', always a string

Tidbit::fromInt(3417520156305973838);
Tidbit::fromString('8w7E2QwnGMb');
Tidbit::isValid('8w7E2QwnGMb'); // true
```

Keep one factory per process (e.g. a shared service). IDs from `create()` on the same factory strictly increase, even within the same millisecond.

## Layout

```
| 41 bits: ms since 2001-01-01 | node bits | counter bits |   (63 bits total, sign bit unused)
                                 \______ 22 bits ______/
```

- **Time**: 41 bits of milliseconds. Valid range: 2001-01-01T00:00:00.000Z to 2070-09-07T15:47:35.551Z.
- **Node**: 10 bits by default. Random per factory unless set.
- **Counter**: the remaining bits. Starts at a random value each millisecond, then increments.

On counter overflow, or when the clock goes backwards, the factory moves to the next millisecond instead of waiting.

### Choosing node bits

```php
new TidbitFactory();                      // random node, 10 node bits / 12 counter bits
new TidbitFactory(node: 7);               // fixed node: no collisions between distinct nodes
new TidbitFactory(nodeBits: 0);           // single host: all 22 bits for the counter
```

With a random node, two processes that each generate one ID in the same millisecond collide with a probability of about 1 in 2 million. Assign distinct nodes if you need a guarantee.

## Backfilling

`createAt()` builds an ID for a given time, e.g. when importing records that already have a date.

```php
$factory->createAt($post->getPublishedAt());   // 2004-03-15T09:30:00+00:00
// 423774860084961455, '1z47Pymsk3Y'
```

- Any `DateTimeInterface`, any timezone. Sub-millisecond precision is truncated.
- Throws `OutOfRangeException` outside the valid range (see [Layout](#layout)).
- Node bits as usual. The counter is random over its full range on every call.
- Leaves `create()` state alone, so you can mix both on the same factory.

IDs from `createAt()` for the same millisecond are **not** guaranteed unique or ordered. With the default 10/12 split, two calls on the same factory for the same millisecond collide with a probability of 1 in 4,096. For `n` records sharing a millisecond, roughly `n² / 8,192`.

Source dates often have second precision, so all records published in the same second share a millisecond. To lower the odds, use a dedicated factory with `nodeBits: 0` (1 in about 4.2 million per pair), and keep a unique constraint on the column so a collision fails loudly instead of silently.

## Clock

The factory takes any [PSR-20](https://www.php-fig.org/psr/psr-20/) clock. Without one, it reads the system time.

```php
// symfony/clock
new TidbitFactory(clock: Clock::get());          // global clock: ClockSensitiveTrait can freeze it in tests
new TidbitFactory(clock: new MonotonicClock());  // wall time at start, then hrtime: ignores NTP jumps
new TidbitFactory(clock: new MockClock('2026-06-01 12:00:00'));

// lcobucci/clock
new TidbitFactory(clock: SystemClock::fromUTC());
new TidbitFactory(clock: new FrozenClock(new \DateTimeImmutable('2026-06-01 12:00:00')));

// beste/clock
new TidbitFactory(clock: SystemClock::create());
new TidbitFactory(clock: FrozenClock::at(new \DateTimeImmutable('2026-06-01 12:00:00')));

// nesbot/carbon: follows Carbon::setTestNow()
new TidbitFactory(clock: new FactoryImmutable());
```

In a Symfony app, the `clock` service autowires into the `$clock` argument.

## Caveats

- Base58 is case-sensitive. Store the integer, not the string. If you must store the string, use `CHAR(11)` with a binary collation (`ascii_bin` in MySQL).
- The ID reveals its creation time, or the time passed to `createAt()`.
- Ordering across processes is by millisecond only. Within one millisecond, IDs from different nodes are not ordered by real time.

## Development

Docker setup with PHP 8.5 and pcov. Run `make` to list targets.

```bash
make build
make start
make install
make test
```

Also `make stan`, `make cs` and `make coverage` (HTML report in `build/`).

The clock (PSR-20) and the `Random\Randomizer` are injectable for deterministic tests.

## License

MIT. See [LICENSE](LICENSE).
