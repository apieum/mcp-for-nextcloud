<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Mcp\Confirmation;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/** A planned write is usable once, under a live account mandate or after an explicit browser approval. */
final class WriteApprovals {
	public const ARGUMENT = 'approval_id';
	private const TTL = 600;

	public function __construct(
		private ApprovalStore $store,
		private AccountMandate $mandate,
		private PlanState $state,
		private ITimeFactory $clock,
		private ICrypto $crypto,
		private LoggerInterface $logger,
	) {}

	/** @param array<string, mixed> $arguments validated arguments of the planned execution */
	public function stage(string $tool, string $uid, array $arguments, string $rendered): string {
		$now = $this->clock->getTime();
		$id = bin2hex(random_bytes(16));
		$this->store->insert([
			'id' => $id,
			'uid' => $uid,
			'fingerprint' => $this->fingerprint($tool, $uid, $arguments),
			'display' => $this->crypto->encrypt($rendered),
			'expires' => $now + self::TTL,
		], $now);
		return $id;
	}

	public function review(string $id, string $uid): ?string {
		$row = $this->store->find($id, $uid, $this->clock->getTime());
		return $row === null ? null : $this->crypto->decrypt($row['display']);
	}

	public function approve(string $id, string $uid): bool {
		return $this->store->approve($id, $uid, $this->clock->getTime());
	}

	public function underMandate(string $uid): bool {
		return $this->mandate->active($uid);
	}

	/** @param array<string, mixed> $arguments validated arguments to be executed */
	public function authorize(string $id, string $tool, string $uid, array $arguments): bool {
		$now = $this->clock->getTime();
		$row = $this->store->find($id, $uid, $now);
		if ($row === null || !hash_equals($row['fingerprint'], $this->fingerprint($tool, $uid, $arguments))) {
			return false;
		}
		$underMandate = (int)$row['approved'] !== 1 && $this->mandate->active($uid);
		if ((!$underMandate && (int)$row['approved'] !== 1) || !$this->store->consume($id, $uid, $now, $underMandate)) {
			return false;
		}
		$this->logger->notice('MCP write authorized for {uid}: {tool} ({source})', [
			'app' => 'mcp', 'uid' => $uid, 'tool' => $tool, 'source' => $underMandate ? 'account mandate' : 'browser approval',
		]);
		return true;
	}

	/** @param array<string, mixed> $arguments validated arguments of the planned execution */
	private function fingerprint(string $tool, string $uid, array $arguments): string {
		unset($arguments[WriteGate::CONFIRM], $arguments[self::ARGUMENT]);
		ksort($arguments);
		return $this->state->of($tool, $uid, ['approval' => $arguments]);
	}
}
