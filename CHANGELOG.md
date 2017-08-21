# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [4.0.1] - 2017-08-21
### Changed
- Check and react on failures when reading/setting stuff in the cache.

## [4.0.0] - 2017-06-16
### Changed
- Update "php-amqplib/rabbitmq-bundle" dependency. Don't use printedcom's fork anymore.
  To fix the breaking change, please change:
```yml
graceful_max_execution_timeout: 1800
graceful_max_execution_timeout_exit_code: 10
```
to
```yml
graceful_max_execution:
    timeout: 1800
    exit_code: 10
```
in your consumers' configuration for the rabbitmq bundle, if you were using this feature. 

## [3.2.1] - 2017-05-12
### Fixed
- Fix doctrine entities being cached between consumers' runs by clearing the entity manager before
  consumers are run

## [3.2.0] - 2017-05-02
### Added
- New Deployments Detection feature, which causes workers to exit, if they run code from a previous
  project deployment

## [3.1.1] - 2017-04-24
### Changed
- Fix `console.exception` listeners unaware of fatal queue exceptions by actually throwing these exceptions.
The change here is that now consumers will crash instead of continue to the next task
when QueueFatalErrorException is thrown. This shouldn't be a breaking change, because
the consumer should be respawned by something like `supervisord`, as per all other
exceptions.

### Fixed
- Fix trying to read the exchange name in exchange-less usage.
- Fix being unable to run `queue:maintenance:wait` before db migrations, part 2.

## [3.1.0] - 2017-04-21
### Added
- Cli command for requeuing tasks. 

### Fixed
- Being unable to run `queue:maintenance:wait` before db migrations.

## [3.0.0] - 2017-04-20
### Added
- Queue maintenance mode based on cache solution instead of the filesystem.
- [breaking change] New required configuration key: `rabbitmq-queue-bundle.queue_maintenance_strategy.service_name`
- [breaking change] New required configuration key: `rabbitmq-queue-bundle.cache_queue_maintenance_strategy.cache_service_name`

## [2.3.0] - 2017-01-30
### Added
- [Minor breaking change] Add "Ensure vhost exists" command. Requires more configuration items.

## [2.2.0] - 2017-01-27
### Changed
- Log `QueueFatalErrorException`'s child exception in the database if `QueueFatalErrorException` was thrown with a child exception.

## [2.1.0] - 2017-01-27
### Added
- [Minor breaking change] Add required `rabbitmq-queue-bundle.queue_names_prefix` configuration item.

## [2.0.0] - 2017-01-23
### Changed
- [Breaking change] Use exchange-less way of using producers and consumers

[Unreleased]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/4.0.1...HEAD
[4.0.1]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/4.0.0...4.0.1
[4.0.0]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/3.2.1...4.0.0
[3.2.1]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/3.2.0...3.2.1
[3.2.0]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/3.1.1...3.2.0
[3.1.1]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/3.1.0...3.1.1
[3.1.0]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/3.0.0...3.1.0
[3.0.0]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/2.3.0...3.0.0
[2.3.0]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/2.2.0...2.3.0
[2.2.0]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/printedcom/rabbitmq-queue-bundle/compare/1.0.0...2.0.0
