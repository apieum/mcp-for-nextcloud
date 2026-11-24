<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Tests\Unit\Mcp\Confirmation;

use OCA\Mcp\Mcp\Confirmation\BrowserConfirmation;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrowserConfirmationTest extends TestCase {
	#[DataProvider('credentials')]
	public function testOnlyAnEnabledBrowserAccountCanConfirm(string $authorization, string $sessionKey, bool $enabled, ?string $impersonator, ?string $expected): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnMap([['Authorization', $authorization], ['PHP_AUTH_USER', '']]);
		$session = $this->createMock(ISession::class);
		$session->method('exists')->willReturnCallback(static fn (string $key): bool => $key === $sessionKey);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$user->method('isEnabled')->willReturn($enabled);
		$users = $this->createMock(IUserSession::class);
		$users->method('getUser')->willReturn($user);
		$users->method('getImpersonatingUserID')->willReturn($impersonator);
		self::assertSame($expected, (new BrowserConfirmation($users, $session))->uid($request));
	}

	public static function credentials(): array {
		return [
			['', '', true, null, 'alice'],
			['', '', true, 'administrator', null],
			['', '', false, null, null],
			['Bearer token', '', true, null, null],
			['Basic token', '', true, null, null],
			['', 'app_password', true, null, null],
			['', 'one_time_token', true, null, null],
		];
	}
}
