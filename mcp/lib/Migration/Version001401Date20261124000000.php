<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Migration;

use Closure;
use OCA\Mcp\Mcp\Confirmation\ApprovalStore;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version001401Date20261124000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable(ApprovalStore::TABLE)) {
			return null;
		}
		$table = $schema->createTable(ApprovalStore::TABLE);
		$table->addColumn('id', Types::STRING, ['length' => 32, 'notnull' => true]);
		$table->addColumn('uid', Types::STRING, ['length' => 255, 'notnull' => true]);
		$table->addColumn('fingerprint', Types::STRING, ['length' => 64, 'notnull' => true]);
		$table->addColumn('display', Types::TEXT, ['notnull' => true]);
		$table->addColumn('approved', Types::SMALLINT, ['default' => 0, 'notnull' => true]);
		$table->addColumn('expires', Types::BIGINT, ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['expires'], 'mcp_wr_approval_expiry');
		return $schema;
	}
}
