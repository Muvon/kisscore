<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Plugin\Data\DatabaseTrait;

/**
 * Exposes the (otherwise protected) pure SQL-building helpers of DatabaseTrait.
 * These compose query strings without touching a database, so they are unit
 * testable and are where injection/correctness bugs would be most costly.
 */
final class SqlBuilderHarness {
	use DatabaseTrait;

	/**
	 * @param array<string,mixed> $conditions
	 * @return array<string>
	 */
	public function whereClause(array &$conditions): array {
		return $this->dbGetWhere($conditions);
	}

	/**
	 * @param array<int|string,mixed> $params
	 * @return string
	 */
	public static function setString(array $params, string $sep = ',', bool $incremental = false): string {
		return self::dbGetSqlStringByParams($params, $sep, $incremental);
	}
}

final class DatabaseSqlBuilderTest extends TestCase {
	private static function norm(string $sql): string {
		return trim((string)preg_replace('/\s+/', ' ', $sql));
	}

	public function testSetStringAssignments(): void {
		$this->assertSame('`a` = :a , `b` = :b', self::norm(SqlBuilderHarness::setString(['a' => 1, 'b' => 2])));
	}

	public function testSetStringIncremental(): void {
		$this->assertSame('`n` = `n` + :n', self::norm(SqlBuilderHarness::setString(['n' => 5], ',', true)));
	}

	public function testSetStringPositionalColumns(): void {
		$this->assertSame('`col1` , `col2`', self::norm(SqlBuilderHarness::setString(['col1', 'col2'])));
	}

	public function testSetStringCustomSeparator(): void {
		$this->assertSame(
			'`a` = :a AND `b` = :b',
			self::norm(SqlBuilderHarness::setString(['a' => 1, 'b' => 2], 'AND'))
		);
	}

	public function testWhereEquals(): void {
		$harness = new SqlBuilderHarness();
		$conditions = ['name' => 'bob'];
		$where = $harness->whereClause($conditions);
		$this->assertSame('`name` = :name', self::norm($where[0]));
	}

	public function testWhereOperatorSuffixes(): void {
		$harness = new SqlBuilderHarness();
		$cases = [
			'age:gt' => '`age` > :age_gt',
			'age:ge' => '`age` >= :age_ge',
			'age:lt' => '`age` < :age_lt',
			'age:le' => '`age` <= :age_le',
			'age:ne' => '`age` != :age_ne',
		];
		foreach ($cases as $key => $expected) {
			$conditions = [$key => 18];
			$where = $harness->whereClause($conditions);
			$this->assertSame($expected, self::norm($where[0]), "operator for $key");
		}
	}

	public function testWhereLikeIsCaseInsensitive(): void {
		$harness = new SqlBuilderHarness();
		$conditions = ['name:~' => 'bo'];
		$where = $harness->whereClause($conditions);
		$this->assertSame('LOWER(`name`) LIKE LOWER(:name_~)', self::norm($where[0]));
	}

	public function testWhereInClauseExpandsAndRewritesParams(): void {
		$harness = new SqlBuilderHarness();
		$conditions = ['id' => [1, 2, 3]];
		$where = $harness->whereClause($conditions);
		$this->assertSame('`id` IN (:ID1$, :ID2$, :ID3$)', self::norm($where[0]));
		$this->assertSame(1, $conditions['ID1$']);
		$this->assertSame(3, $conditions['ID3$']);
		$this->assertArrayNotHasKey('id', $conditions);
	}

	public function testWhereRange(): void {
		$harness = new SqlBuilderHarness();
		$conditions = ['ts:range' => [10, 20]];
		$where = $harness->whereClause($conditions);
		$this->assertSame('(`ts` BETWEEN :ID1$ AND :ID2$)', self::norm($where[0]));
	}

	public function testWhereEmptyArrayBecomesNull(): void {
		$harness = new SqlBuilderHarness();
		$conditions = ['x' => []];
		$where = $harness->whereClause($conditions);
		$this->assertSame('`x` = NULL', self::norm($where[0]));
	}

	public function testWhereCombinesMultipleConditions(): void {
		$harness = new SqlBuilderHarness();
		$conditions = ['status' => 'active', 'age:ge' => 21];
		$where = $harness->whereClause($conditions);
		$this->assertCount(2, $where);
	}
}
