# Internals

> The tests use a connection to a running Redis cluster. If you are not using Docker, you must start the cluster
> yourself before running the tests.

## Testing in Docker

### Prepare

```shell
# {{ v }} = 8.0, 8.1, 8.2. Default PHP 8.1
make build v=8.1
```

### Unit testing

```shell
# {{ v }} = 8.0, 8.1, 8.2. Default PHP 8.1
make test v=8.1
```

### Mutation testing

```shell
# {{ v }} = 8.0, 8.1, 8.2. Default PHP 8.1
make mutation-test v=8.0
```

## Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

## Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

## Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## Code style

Use [Rector](https://github.com/rectorphp/rector) to make codebase follow some specific rules or
use either newest or any specific version of PHP:

```shell
./vendor/bin/rector
```

## Dependencies

This package uses [composer-require-checker](https://github.com/maglnet/ComposerRequireChecker) to check if
all dependencies are correctly defined in `composer.json`. To run the checker, execute the following command:

```shell
./vendor/bin/composer-require-checker
```
