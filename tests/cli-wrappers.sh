#!/usr/bin/env bash
set -eu

source_dir=$(cd "$(dirname "$0")/.." && pwd)
project_dir=$(mktemp -d)
trap 'rm -rf "$project_dir"' EXIT
mkdir -p "$project_dir/vendor/muvon/kisscore/bin" "$project_dir/env/run"
cp "$source_dir/bin/php-exec" "$source_dir/bin/php-exec-one" "$project_dir/vendor/muvon/kisscore/bin/"
cat > "$project_dir/vendor/autoload.php" <<'PHP'
<?php
class Env { public static function waitInit(...$args): void {} }
class App {
    public static function start(...$args): void {}
    public static function stop(): void {}
}
class Input { public static function set(...$args): void {} }
PHP
wrappers="$project_dir/vendor/muvon/kisscore/bin"
expect_status() {
  expected=$1
  shift
  actual=0
  "$@" > "$project_dir/output" 2>&1 || actual=$?
  if [ "$actual" -ne "$expected" ]; then
    cat "$project_dir/output"
    echo "Expected exit $expected, got $actual" >&2
    exit 1
  fi
}
expect_status 0 "$wrappers/php-exec" 'echo "ok";'
expect_status 1 "$wrappers/php-exec" 'throw new RuntimeException("expected failure");'
expect_status 37 "$wrappers/php-exec" 'exit(37);'
cat > "$project_dir/failure.php" <<'PHP'
<?php throw new RuntimeException('expected failure');
PHP
expect_status 1 "$wrappers/php-exec-one" "$project_dir/failure.php"
[ -f "$project_dir/env/run/failure.php.lock" ]
expect_status 1 "$wrappers/php-exec-one" "$project_dir/failure.php"

mkfifo "$project_dir/ready" "$project_dir/release"
cat > "$project_dir/locked.php" <<'PHP'
<?php
file_put_contents(__DIR__ . '/ready', "ready\n");
$release = fopen(__DIR__ . '/release', 'r');
fgets($release);
fclose($release);
PHP
"$wrappers/php-exec-one" "$project_dir/locked.php" > "$project_dir/holder-output" 2>&1 &
holder=$!
read -r ready < "$project_dir/ready"
[ "$ready" = ready ]
expect_status 1 "$wrappers/php-exec-one" "$project_dir/locked.php"
echo release > "$project_dir/release"
wait "$holder"
[ -f "$project_dir/env/run/locked.php.lock" ]
cat > "$project_dir/locked.php" <<'PHP'
<?php echo 'unlocked';
PHP
expect_status 0 "$wrappers/php-exec-one" "$project_dir/locked.php"
echo 'CLI wrappers: success, exception, explicit exit, failure propagation, lock contention and lock release passed'
