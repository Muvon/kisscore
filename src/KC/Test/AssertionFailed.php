<?php declare(strict_types=1);

namespace KC\Test;

/**
 * Thrown by a failed `assert_*()`. A distinct type so the Runner can tell an
 * assertion failure apart from an unexpected error in the code under test —
 * both fail the test, but the report labels them differently.
 */
final class AssertionFailed extends \RuntimeException {
}
