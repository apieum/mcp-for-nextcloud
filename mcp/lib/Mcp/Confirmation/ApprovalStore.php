<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Mcp\Confirmation;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Persists short-lived write plans and atomically consumes an approval across requests. */
class ApprovalStore {
	public const TABLE = 'mcp_write_approvals';

	public function __construct(private IDBConnection $db) {}

	/** @param array{id:string,uid:string,fingerprint:string,display:string,expires:int} $row */
	public function insert(array $row, int $now): void {
		$purge = $this->db->getQueryBuilder();
		$purge->delete(self::TABLE)
			->where($purge->expr()->lte('expires', $purge->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		$insert = $this->db->getQueryBuilder();
		$insert->insert(self::TABLE);
		foreach ($row as $column => $value) {
			$insert->setValue($column, $insert->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
		}
		$insert->executeStatement();
	}

	/** @return array{fingerprint:string,display:string,approved:int|string}|null */
	public function find(string $id, string $uid, int $now): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('fingerprint', 'display', 'approved')->from(self::TABLE);
		$this->unexpired($qb, $id, $uid, $now);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return is_array($row) ? $row : null;
	}

	public function approve(string $id, string $uid, int $now): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)->set('approved', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT));
		$this->unexpired($qb, $id, $uid, $now);
		$qb->andWhere($qb->expr()->eq('approved', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement() === 1;
	}

	public function consume(string $id, string $uid, int $now, bool $underMandate): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE);
		$this->unexpired($qb, $id, $uid, $now);
		if (!$underMandate) {
			$qb->andWhere($qb->expr()->eq('approved', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		}
		return $qb->executeStatement() === 1;
	}

	private function unexpired(IQueryBuilder $qb, string $id, string $uid, int $now): void {
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gt('expires', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)));
	}
}
