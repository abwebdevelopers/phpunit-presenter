# PHPUnit Presenter

Displays the PHPUnit test results in a more presentable and friendly format. The Presenter includes timings for tests,
colour support and better displays of errors and failures.

![Presenter Output](https://abweb.com.au/img/presenter.png)

**This has _only_ been tested with PHPUnit `^7.5`**. However, this plugin still allows PHPUnit up to `^9.0` (via composer.json) to ease dependency restrictions but please note that things may not work as expected (use at your own 'risk').

## Features

- Different formatting types depending on your preferences.
- Grouping of test cases and suites.
- Time taken for each test case.
- Optional colour support.

## How to use

Simply include this library via Composer:

```
composer require --dev abwebdevelopers/phpunit-presenter
```

And then add it to your `phpunit.xml` file as the `printerClass` attribute

```xml
<phpunit
        ...
        printerClass="ABWebDevelopers\PHPUnitPresenter\Presenter"
        ...
>
```

## Configuration

The Presenter can be configured via environment variables. The following environment variables are used:

Environment Variable | Default | Description
---------------------|---------|------------
`PRESENTER_SHOW_TIMES` | `1` | Displays the time taken for test cases. Set to `0` to disable.
`PRESENTER_COLOURS` | `1` | Displays colouring of CLI output. Set to `0` to disable. It is recommended to disable colours for test environments, ie. Travis CI.
`PRESENTER_HIDE_SUCCESSFUL` | `0` | If enabled, will hide all successful tests, and only show failed or errored tests. Set to `1` to enable.
`PRESENTER_FORMAT` | `default` | Sets the format used to display results via Presenter. Valid values are `default`, `feed` and `condensed`. It is recommended to use `feed` or `condensed` for test environments, ie. Travis CI.

## Formats

There are three types of formatting of the results displayed by Presenter - `default`, `feed` and `condensed`.

`default` and `feed` display the same information (shown in the screenshot above), however, `default` uses CLI line overwriting to show real-time progress of tests. This may not work with some log readers used in test environments (such as Travis CI), so it is recommended to use `feed` for test environments.

A third format `condensed` presents test results in a condensed format closer to PHPUnit's default result printer. When using `condensed`, it is implied that `PRESENTER_SHOW_TIMES` is set to `0`. The formatting of condensed looks similar to this:

![Presenter Condensed Output](https://abweb.com.au/img/presenter-condensed.png)

## License

MIT.