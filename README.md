# Tidbit

Short, time-sorted, 64-bit identifiers for PHP.

```
1FcgTdmAm33   <->   108556555512373838
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

$id->toInt();          // 108556555512373838, store this
$id->toString();       // '1FcgTdmAm33', expose this
$id->getDateTime();    // 2026-10-27T13:24:59.717+00:00
json_encode($id);      // '"1FcgTdmAm33"', always a string

Tidbit::fromInt(108556555512373838);
Tidbit::fromString('1FcgTdmAm33');
Tidbit::isValid('1FcgTdmAm33'); // true
```

Keep one factory per process (e.g. a shared service). IDs from the same factory strictly increase, even within the same millisecond.

## Layout

```
| 41 bits: ms since 2026-01-01 | node bits | counter bits |   (63 bits total, sign bit unused)
                                 \______ 22 bits ______/
```

- **Time**: 41 bits of milliseconds, valid until about 2095.
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
- The ID reveals its creation time.
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
