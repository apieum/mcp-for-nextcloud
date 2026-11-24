<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Mcp\Confirmation;

use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;

/** Extra credential checks for the browser routes, in addition to Nextcloud's session and CSRF checks. */
final class BrowserConfirmation {
	public function __construct(private IUserSession $users, private ISession $session) {}

	public function uid(IRequest $request): ?string {
		if ($request->getHeader('Authorization') !== '' || $request->getHeader('PHP_AUTH_USER') !== ''
			|| $this->session->exists('app_password') || $this->session->exists('one_time_token')
			|| $this->users->getImpersonatingUserID() !== null) {
			return null;
		}
		$user = $this->users->getUser();
		return $user !== null && $user->isEnabled() ? $user->getUID() : null;
	}
}
