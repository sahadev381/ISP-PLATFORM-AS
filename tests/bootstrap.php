<?php
/**
 * A tiny zero-dependency test harness.
 *
 * The project has no Composer dev dependencies and CI only installs the
 * runtime ones, so pulling in PHPUnit would add an install step for a
 * handful of assertions. This gives assert/run/report in ~80 lines and
 * runs anywhere PHP does:
 *
 *     php tests/run.php
 *
 * Exit code is 0 when every assertion passes, 1 otherwise, so CI can
 * just call it.
 */

declare(strict_types=1);

final class TestRunner
{
    private int $passed = 0;
    /** @var array<int, string> */
    private array $failures = [];
    private string $group = '';

    public function group(string $name): void
    {
        $this->group = $name;
        echo "\n{$name}\n";
    }

    /** Assert strict equality. */
    public function is(string $label, mixed $actual, mixed $expected): void
    {
        if ($actual === $expected) {
            $this->pass($label);
            return;
        }
        $this->fail($label, sprintf(
            "expected %s\n       actual   %s",
            $this->show($expected),
            $this->show($actual)
        ));
    }

    public function true(string $label, mixed $actual): void
    {
        $this->is($label, $actual, true);
    }

    public function false(string $label, mixed $actual): void
    {
        $this->is($label, $actual, false);
    }

    /** Assert that $needle does not appear in $haystack. */
    public function lacks(string $label, string $haystack, string $needle): void
    {
        if (strpos($haystack, $needle) === false) {
            $this->pass($label);
            return;
        }
        $this->fail($label, sprintf(
            "must not contain %s\n       actual        %s",
            $this->show($needle),
            $this->show($haystack)
        ));
    }

    /** Assert that calling $fn raises a Throwable. */
    public function throws(string $label, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable) {
            $this->pass($label);
            return;
        }
        $this->fail($label, 'expected an exception, none was thrown');
    }

    private function pass(string $label): void
    {
        $this->passed++;
        echo "  ok   {$label}\n";
    }

    private function fail(string $label, string $detail): void
    {
        $this->failures[] = "{$this->group}: {$label}";
        echo "  FAIL {$label}\n       {$detail}\n";

        // Also emit a GitHub Actions annotation. Annotations show up in
        // the run summary, which is readable without downloading the
        // job log - and the log is not always reachable.
        if (getenv('GITHUB_ACTIONS') === 'true') {
            $oneLine = str_replace(["\r", "\n"], ' ', "{$this->group}: {$label} - {$detail}");
            echo "::error::{$oneLine}\n";
        }
    }

    private function show(mixed $value): string
    {
        return is_string($value) ? var_export($value, true) : json_encode($value);
    }

    public function report(): int
    {
        $failed = count($this->failures);
        echo sprintf(
            "\n%d assertions, %d passed, %d failed\n",
            $this->passed + $failed,
            $this->passed,
            $failed
        );
        foreach ($this->failures as $f) {
            echo "  - {$f}\n";
        }
        return $failed === 0 ? 0 : 1;
    }
}
