# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2025-03-16

### Fixed

- Fix some PHP warnings when a context does not contain a base_url context setting

## [1.0.1] - 2024-01-09

### Fixed

- Don't run the OnHandleRequest event if MODX runs in connector mode

## [1.0.0] - 2023-10-15

### Added

- Complete refactored code of XRouting:
  - Use a service class to collect global settings and methods in one place to avoid duplicated code
  - Separate the plugin events code into event based classes
  - Fix some PHP warnings - i.e. PHP warning: Undefined array key "smartrouting-debug"
