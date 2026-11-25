<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Modules\Deck\Commenting;

use OCA\Mcp\L10n\Translator;
use OCA\Mcp\Mcp\Confirmation\PlanState;
use OCA\Mcp\Mcp\Tools\Results\ToolFailure;
use OCA\Mcp\Modules\Deck\Integration\{DeckCardComments, DeckErrors};
use OCA\Mcp\Modules\Deck\Tools\AddCardComment;

final class AddCardCommentPreview {
	public function __construct(private DeckCardComments $comments, private PlanState $state) {
	}

	/** @param array<string, mixed> $arguments @return array<string, mixed> Read-only signed preview. */
	public function plan(array $arguments, string $uid): array {
		if (isset($arguments['expectedActorId']) && $arguments['expectedActorId'] !== $uid) {
			throw new ToolFailure(Translator::t('The authenticated account does not match the expected comment author.'));
		}
		if (preg_match('/^[\s\p{Z}]*$/u', $arguments['message']) === 1) {
			throw new ToolFailure(Translator::t('Enter a non-blank Deck comment.'));
		}
		try {
			$target = $this->comments->target($uid, $arguments['boardId'], $arguments['cardId']);
		} catch (\Throwable $e) {
			throw new ToolFailure(DeckErrors::messageFor($e), previous: $e);
		}

		$plan = $target + [
			'actorId' => $uid,
			'message' => $arguments['message'],
			'requiresSharedConfirmation' => $target['board']['owner'] !== $uid || $target['audience'] !== [],
		];
		return $plan + [PlanState::ARGUMENT => $this->state->of(AddCardComment::TOOL, $uid, $plan)];
	}
}
