<?php declare(strict_types=1);

namespace ABWebDevelopers\PHPUnitPresenter;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Util\Printer;
use League\CLImate\CLImate;

class Presenter extends Printer implements TestListener
{
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILURE = 'failure';
    const STATUS_ERROR = 'error';

    /**
     * Presenter configuration
     *
     * @var array
     */
    protected $config;

    /**
     * CLImate CLI terminal process.
     *
     * @var League\CLImate\CLImate
     */
    protected $cli;

    /**
     * The tests that have been run
     *
     * @var array
     */
    protected $tests = [];

    /**
     * Current suite of tests running
     *
     * @var TestSuite
     */
    protected $currentSuite;

    /**
     * Have we begun the tests?
     *
     * @var boolean
     */
    protected $begun = false;

    /**
     * Constructor.
     *
     * Generates a new CLImate CLI instance and configures the Presenter based on environment variables.
     *
     * @param null|mixed $out
     */
    public function __construct($out = null)
    {
        $this->cli = new CLImate;

        $this->config = [
            'format' => $this->getFormat(),
            'showTimes' => (bool) $this->env('PRESENTER_SHOW_TIMES', true),
            'colours' => (bool) $this->env('PRESENTER_COLOURS', true),
            'hideSuccessful' => (bool) $this->env('PRESENTER_HIDE_SUCCESSFUL', false),
        ];
    }

    /**
     * Buffer flush method.
     *
     * This is overriden from PHPUnit's default result printer to print the results, as well as exceptions and errors
     * that were encountered during the tests.
     *
     * @return void
     */
    public function flush(): void
    {
        if ($this->config['format'] === 'condensed') {
            $this->cli->br();
        }

        $this->cli->br();
        if ($this->config['colours']) {
            $this->cli->bold()->underline()->blue()->out('Results');
        } else {
            $this->cli->out('Results');
        }
        $this->cli->br();

        // Results
        $this->printResults();
        $this->printFailures();
        $this->printErrors();
    }

    /**
     * Incremental flush method.
     *
     * This has been stubbed out from the original PHPUnit Printer method.
     *
     * @return void
     */
    public function incrementalFlush(): void
    {
    }

    /**
     * Buffer write method.
     *
     * This has been stubbed out from the original PHPUnit Printer method.
     *
     * @param string $buffer
     * @return void
     */
    public function write(string $buffer): void
    {
    }

    /**
     * Get auto buffer flush method.
     *
     * This has been stubbed out from the original PHPUnit Printer method.
     *
     * @return boolean
     */
    public function getAutoFlush(): bool
    {
        return true;
    }

    /**
     * Set auto buffer flush method.
     *
     * This has been stubbed out from the original PHPUnit Printer method.
     *
     * @param boolean $autoFlush
     * @return void
     */
    public function setAutoFlush(bool $autoFlush): void
    {
    }

    /**
     * Add an error during tests.
     *
     * When a unit test encounters an error, it is recorded in the run tests in order to display in the results.
     *
     * @param Test $test The current test case.
     * @param \Throwable $t The thrown error message.
     * @param float $time The time taken for this test case.
     * @return void
     */
    public function addError(Test $test, \Throwable $t, float $time): void
    {
        $this->tests[$this->getSignature($test)]['status'] = self::STATUS_ERROR;
        $this->tests[$this->getSignature($test)]['errors'][] = $t;
    }

    /**
     * Add a warning during tests.
     *
     * When a unit test encounters an warning, it is recorded in the run tests in order to display in the results.
     *
     * @param Test $test The current test case.
     * @param Warning $e The thrown warning message.
     * @param float $time The time taken for this test case.
     * @return void
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
    }

    /**
     * Add a test failure during tests.
     *
     * When a unit test encounters a failed assertion, it is recorded in the run tests in order to display in the
     * results.
     *
     * @param Test $test The current test case.
     * @param AssertionFailedError $e The thrown assertion failure message.
     * @param float $time The time taken for this test case.
     * @return void
     */
    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $diff = null;

        // Find test case line
        foreach ($e->getTrace() as $i => $trace) {
            if ($trace['class'] === $this->tests[$this->getSignature($test)]['suite']) {
                $assertion = $e->getTrace()[$i - 1];

                // Add diff if needed
                if (in_array($assertion['function'], ['assertEquals', 'assertNotEquals'])) {
                    $diff = [
                        'actual' => (string) $assertion['args'][0],
                        'expected' => (string) $assertion['args'][1]
                    ];
                }
            }
        }

        $this->tests[$this->getSignature($test)]['status'] = self::STATUS_FAILURE;
        $this->tests[$this->getSignature($test)]['errors'][] = [
            'message' => $e->getMessage(),
            'file' => $assertion['file'],
            'line' => $assertion['line'],
            'diff' => $diff
        ];
    }

    /**
     * Incomplete test.
     */
    public function addIncompleteTest(Test $test, \Throwable $t, float $time): void
    {
    }

    /**
     * Risky test.
     */
    public function addRiskyTest(Test $test, \Throwable $t, float $time): void
    {
    }

    /**
     * Skipped test.
     */
    public function addSkippedTest(Test $test, \Throwable $t, float $time): void
    {
    }

    /**
     * Callback fired when a test suite is started.
     *
     * @param TestSuite $suite The test suite that was started.
     * @return void
     */
    public function startTestSuite(TestSuite $suite): void
    {
        if (!$this->begun) {
            $this->showHeader();
        }

        $this->currentSuite = $suite;

        // Are we dealing with an actual suite, or a test case class?
        $isClass = class_exists($suite->getName());

        if ($isClass) {
            $count = ($suite->count() === 1) ? '1 test' : $suite->count() . ' tests';

            $this->cli->br();

            if ($this->config['format'] === 'condensed') {
                if ($this->config['colours']) {
                    $this->cli->bold()->inline($suite->getName() . ' ');
                } else {
                    $this->cli->inline($suite->getName() . ' ');
                }
            } else {
                if ($this->config['colours']) {
                    $this->cli->bold()->out($suite->getName() . ' <yellow>(' . $count . ')</yellow>');
                } else {
                    $this->cli->out($suite->getName() . ' (' . $count . ')');
                }
            }
        } else {
            if ($this->config['colours']) {
                $this->cli->bold()->yellow()->out($suite->getName());
            } else {
                $this->cli->out($suite->getName());
            }
        }
    }

    /**
     * Callback fired when a test suite has been fully tested.
     *
     * @param TestSuite $suite The test suite that was fully tested.
     * @return void
     */
    public function endTestSuite(TestSuite $suite): void
    {
        $this->currentSuite = null;
    }

    /**
     * Callback fired when a test case is started.
     *
     * @param Test $test The test case that was started.
     * @return void
     */
    public function startTest(Test $test): void
    {
        // Output test
        if ($this->config['format'] === 'default' && !$this->config['hideSuccessful']) {
            $this->cli->out('[ ] ' . $test->getName());
        }

        // Add test to our log - tests default to success state
        $this->tests[$this->getSignature($test)] = [
            'suite' => $this->currentSuite->getName(),
            'test' => $test->getName(),
            'assertions' => 0,
            'time' => 0,
            'status' => self::STATUS_SUCCESS,
            'errors' => []
        ];
    }

    /**
     * Callback fired when a test case is tested.
     *
     * @param Test $test The test case that was tested.
     * @param float $time The time taken to run the test case.
     * @return void
     */
    public function endTest(Test $test, float $time): void
    {
        // Set stats
        $this->tests[$this->getSignature($test)]['assertions'] = $test->getNumAssertions();
        $this->tests[$this->getSignature($test)]['time'] = $time;

        if (
            $this->config['hideSuccessful']
            && $this->tests[$this->getSignature($test)]['status'] === self::STATUS_SUCCESS
        ) {
            return;
        }

        if ($this->config['format'] === 'default') {
            fwrite(STDOUT, "\e[1A");
        }

        // Determine colours and symbol for status
        $colour = 'white';
        $symbol = '-';

        switch ($this->tests[$this->getSignature($test)]['status']) {
            case self::STATUS_SUCCESS:
                $colour = 'green';
                $symbol = '✔';
                break;
            case self::STATUS_FAILURE:
                $colour = 'red';
                $symbol = '✗';
                break;
            case self::STATUS_ERROR:
                $colour = 'red';
                $symbol = 'E';
                break;
        }

        $time = $this->getTime($time);

        if ($this->config['format'] === 'condensed') {
            $this->cli->inline($this->colour($colour, $symbol));
        } else {
            $this->cli->inline('[' . $this->colour($colour, $symbol) . '] ' . $test->getName());
            if ($this->config['showTimes']) {
                $this->cli->out(' ' . $this->colour($time['colour'], '(' . $time['amount'] . $time['factor'] . ')'));
            } else {
                $this->cli->br();
            }
        }
    }

    /**
     * Gets an environment variable.
     *
     * @param string $name The name of the environment variable
     * @param mixed $default If no environment variable is found, use this default value.
     * @return mixed
     */
    protected function env(string $name, $default = null)
    {
        $env = getenv($name, true);

        if ($env === false) {
            return $default;
        }

        return $env;
    }

    /**
     * Gets the format of the test results.
     *
     * Available formats:
     *  - "default" - Displays the real-time list of test results and shows progress.
     *  - "feed" - Displays a CI-terminal friendly list of test results, without using special overwrites to display
     *             progress.
     *  - "condensed" - Displays a condensed format for the tests, more closer to the original PHPUnit results display.
     *                  This format implies that "showTimes" is `false`.
     *
     * @return string
     */
    protected function getFormat(): string
    {
        $format = (string) $this->env('PRESENTER_FORMAT', 'default');

        if (!in_array($format, ['default', 'feed', 'condensed'])) {
            $format = 'default';
        }

        return $format;
    }

    /**
     * Prints coloured text, if colours are enabled.
     *
     * @param string $colour The name of the colour.
     * @param mixed $text The text to colour. If not a string, the value will be converted to a string.
     * @return string
     */
    protected function colour(string $colour, $text): string
    {
        if ($this->config['colours']) {
            return '<' . $colour . '>' . ((string) $text) . '</' . $colour . '>';
        }

        return (string) $text;
    }

    /**
     * Shows the header for the Presenter.
     *
     * @return void
     */
    protected function showHeader(): void
    {
        // Clear terminal and show header
        $this->cli->clear();

        if ($this->config['colours']) {
            $this->cli->bold()->underline()->blue()->out('Running PHPUnit tests');
        } else {
            $this->cli->out('Running PHPUnit tests');
        }

        $this->cli->br();

        // We.... have.... begun
        $this->begun = true;
    }

    /**
     * Gets the signature (suite name :: test name) of the test.
     *
     * @param Test $test Test case.
     * @return string
     */
    protected function getSignature(Test $test): string
    {
        return $this->currentSuite->getName() . '::' . $test->getName();
    }

    /**
     * Gets the parameters for time display for each test.
     *
     * Returns an array with the time amount, the factor and the colour to present the time as.
     *
     * @param float $time
     * @return array
     */
    protected function getTime(float $time): array
    {
        // Set time to milliseconds
        $time = $time * 1000;
        $timeFactor = 'ms';
        $timeColour = 'green';

        if ($time >= 400) {
            $timeColour = 'yellow';
        }

        if ($time >= 1000) {
            $timeColour = 'red';
            // Convert milliseconds back to seconds
            $time = $time / 1000;
            $timeFactor = 'secs';

            if ($time >= 60) {
                // Convert time to minutes
                $time = $time / 60;
                $timeFactor = 'mins';
            }
        }

        $timeAmount = round($time, 2);

        return [
            'amount' => $timeAmount,
            'factor' => $timeFactor,
            'colour' => $timeColour
        ];
    }

    /**
     * Gets the average time taken for tests. Uses `getTime()` method for output.
     *
     * @return array
     */
    protected function getAvgTime(): array
    {
        $totalTime = 0;

        foreach ($this->tests as $test) {
            $totalTime += $test['time'];
        }

        $avgTime = $totalTime / $this->getNumTests();

        return $this->getTime($avgTime);
    }

    /**
     * Gets the number of tests run.
     *
     * @return integer
     */
    protected function getNumTests(): int
    {
        return count($this->tests);
    }

    /**
     * Gets the number of assertions made.
     *
     * @return integer
     */
    protected function getNumAssertions(): int
    {
        $assertions = 0;

        foreach ($this->tests as $test) {
            $assertions += $test['assertions'];
        }

        return $assertions;
    }

    /**
     * Gets the number of failures.
     *
     * @return integer
     */
    protected function getNumFailures(): int
    {
        return $this->getNumFromStatus(self::STATUS_FAILURE);
    }

    /**
     * Gets all tests that failed.
     *
     * @return array
     */
    protected function getFailures(): array
    {
        return $this->getTestsFromStatus(self::STATUS_FAILURE);
    }

    /**
     * Gets the number of errors.
     *
     * @return integer
     */
    protected function getNumErrors(): int
    {
        return $this->getNumFromStatus(self::STATUS_ERROR);
    }

    /**
     * Gets all tests that errored out.
     *
     * @return array
     */
    protected function getErrors(): array
    {
        return $this->getTestsFromStatus(self::STATUS_ERROR);
    }

    /**
     * Gets the number of tests that match a specific status.
     *
     * @param string $status One of the STATUS_* constants.
     * @return integer
     */
    protected function getNumFromStatus(string $status): int
    {
        $found = 0;

        foreach ($this->tests as $test) {
            if ($test['status'] === $status) {
                ++$found;
            }
        }

        return $found;
    }

    /**
     * Gets the tests that match a specific status.
     *
     * @param string $status One of the STATUS_* constants.
     * @return array
     */
    protected function getTestsFromStatus(string $status): array
    {
        $tests = [];

        foreach ($this->tests as $test => $details) {
            if ($details['status'] === $status) {
                $tests[$test] = $this->tests[$test];
            }
        }

        return $tests;
    }

    /**
     * Gets a line from a file. Used to show a line where an assertion failed.
     *
     * @param string $file
     * @param integer $lineNum
     * @return string
     */
    protected function getLine(string $file, int $lineNum): ?string
    {
        if (!file_exists($file)) {
            return null;
        }

        $fp = fopen($file, 'r');
        if (!$fp) {
            return null;
        }

        $currentLine = 0;
        $foundLine = null;
        while (!feof($fp)) {
            ++$currentLine;
            $line = fgets($fp);

            if ($currentLine === $lineNum) {
                $foundLine = $line;
                break;
            }
        }
        fclose($fp);

        return preg_replace('/^[\t\s]+(.*?)[\r\n]*$/', '$1', $foundLine);
    }

    /**
     * Prints the resulting stats of all tests run.
     *
     * @return void
     */
    protected function printResults(): void
    {
        $avgTime = $this->getAvgTime();
        $this->cli->out($this->colour('blue', 'Tests run: ') . $this->getNumTests());
        $this->cli->out($this->colour('blue', 'Assertions made: ') . $this->getNumAssertions());
        $this->cli->out(
            $this->colour('blue', 'Avg. test time: ') .
            $this->colour($avgTime['colour'], $avgTime['amount'] . $avgTime['factor'])
        );
        $this->cli->out(
            $this->colour('blue', 'Failures: ') . (($this->getNumFailures() > 0)
            ? $this->colour('red', $this->getNumFailures())
            : 0)
        );
        $this->cli->out(
            $this->colour('blue', 'Errors: ') . (($this->getNumErrors() > 0)
            ? $this->colour('red', $this->getNumErrors())
            : 0)
        );
    }

    /**
     * Prints the failures that were encountered in the unit tests.
     *
     * @return void
     */
    protected function printFailures(): void
    {
        if ($this->getNumFailures() === 0) {
            return;
        }

        $this->cli->br();
        if ($this->config['colours']) {
            $this->cli->bold()->underline()->red()->out('Failures');
        } else {
            $this->cli->out('Failures');
        }
        $this->cli->br();

        $first = true;
        foreach ($this->getFailures() as $i => $details) {
            if ($first === false) {
                $this->cli->br();
                $this->cli->out('-----');
                $this->cli->br();
            }
            $first = false;

            // Find offending line
            $line = $this->getLine($details['errors'][0]['file'], $details['errors'][0]['line']);

            if ($this->config['colours']) {
                $this->cli->bold()->inline($details['suite'] . '::');
                $this->cli->out($details['test']);
                $this->cli->darkGray()->out($details['errors'][0]['file']);
                $this->cli->red()->out($details['errors'][0]['message']);
                $this->cli->br();
                $this->cli->out('Line ' . $details['errors'][0]['line'] . ' | ' . $line);

                if (isset($details['errors'][0]['diff'])) {
                    $this->cli->green()->out('Expected: ' . $details['errors'][0]['diff']['expected']);
                    $this->cli->red()->out('Actual:   ' . $details['errors'][0]['diff']['actual']);
                }
            } else {
                $this->cli->inline($details['suite'] . '::');
                $this->cli->out($details['test']);
                $this->cli->out($details['errors'][0]['file']);
                $this->cli->out($details['errors'][0]['message']);
                $this->cli->br();
                $this->cli->out('Line ' . $details['errors'][0]['line'] . ' | ' . $line);

                if (isset($details['errors'][0]['diff'])) {
                    $this->cli->out('Expected: ' . $details['errors'][0]['diff']['expected']);
                    $this->cli->out('Actual:   ' . $details['errors'][0]['diff']['actual']);
                }
            }
        }
    }


    /**
     * Prints the failures that were encountered in the unit tests.
     *
     * @return void
     */
    protected function printErrors(): void
    {
        if ($this->getNumErrors() === 0) {
            return;
        }

        $this->cli->br();
        if ($this->config['colours']) {
            $this->cli->bold()->underline()->red()->out('Errors');
        } else {
            $this->cli->out('Errors');
        }
        $this->cli->br();

        $first = true;
        foreach ($this->getErrors() as $i => $details) {
            if ($first === false) {
                $this->cli->br();
                $this->cli->out('-----');
                $this->cli->br();
            }
            $first = false;

            // Find offending line
            $line = $this->getLine($details['errors'][0]->getFile(), $details['errors'][0]->getLine());

            if ($this->config['colours']) {
                $this->cli->bold()->inline($details['suite'] . '::');
                $this->cli->out($details['test']);
                $this->cli->darkGray()->out($details['errors'][0]->getFile());
                $this->cli->red()->out($details['errors'][0]->getMessage());
                $this->cli->br();
                $this->cli->out('Line ' . $details['errors'][0]->getLine() . ' | ' . $line);
                $this->cli->br();
                $this->cli->darkGray()->out($details['errors'][0]->getTraceAsString());
            } else {
                $this->cli->inline($details['suite'] . '::');
                $this->cli->out($details['test']);
                $this->cli->out($details['errors'][0]->getFile());
                $this->cli->out($details['errors'][0]->getMessage());
                $this->cli->br();
                $this->cli->out('Line ' . $details['errors'][0]->getLine() . ' | ' . $line);
                $this->cli->br();
                $this->cli->out($details['errors'][0]->getTraceAsString());
            }
        }
    }
}
