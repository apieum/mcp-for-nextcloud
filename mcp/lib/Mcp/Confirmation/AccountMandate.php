<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Mcp\Confirmation;

use OCP\Config\IUserConfig;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/** The account owner's standing authorization, independent of MCP credentials. */
final class AccountMandate {
	private const KEY = 'write_account_mandate';
	private const OWNER_CONFIG_KEY = 'mcp_write_mandate_owner';

	public function __construct(private IUserConfig $config, private LoggerInterface $logger, private IConfig $systemConfig) {}

	public function active(string $uid): bool {
		$this->config->clearCache($uid);
		$default = $uid !== '' && $uid === $this->systemConfig->getSystemValue(self::OWNER_CONFIG_KEY, '');
		return $this->config->getValueBool($uid, 'mcp', self::KEY, $default);
	}

	public function set(string $uid, bool $enabled): void {
		if ($this->config->setValueBool($uid, 'mcp', self::KEY, $enabled)) {
			$this->logger->notice('MCP account mandate changed by {uid}: {state}', [
				'app' => 'mcp', 'uid' => $uid, 'state' => $enabled ? 'enabled' : 'revoked',
			]);
		}
	}
}
