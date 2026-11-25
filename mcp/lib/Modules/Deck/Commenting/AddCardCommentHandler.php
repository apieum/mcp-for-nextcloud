<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Modules\Deck\Commenting;

use OCA\Mcp\Access\Grants\GrantPolicy;
use OCA\Mcp\L10n\Translator;
use OCA\Mcp\Mcp\Confirmation\{PlanChanged, PlanState};
use OCA\Mcp\Mcp\Tools\Results\{ToolFailure, ToolResult};
use OCA\Mcp\Modules\Deck\Integration\DeckCardComments;
use OCA\Mcp\Modules\Deck\Presentation\DeckRefusalMessages;
use Psr\Log\LoggerInterface;

final class AddCardCommentHandler {
	public function __construct(
		private DeckCardComments $comments,
		private AddCardCommentPreview $plans,
		private GrantPolicy $grants,
		private LoggerInterface $logger,
	) {
	}

	/** @param array<string, mixed> $arguments @return array<string, mixed> MCP response with the observed effect. */
	public function handle(array $arguments, string $uid): array {
		PlanState::require($arguments);
		$plan = $this->plans->plan($arguments, $uid);
		if (!PlanState::matches($plan[PlanState::ARGUMENT], $arguments)) {
			throw new PlanChanged($plan);
		}
		if ($plan['requiresSharedConfirmation'] && ($arguments['confirm_shared'] ?? false) !== true) {
			return ToolResult::structured(Translator::t('Acknowledge that the board audience can read the comment.'),
				$plan + ['requiresConfirmation' => true]);
		}
		if (!$this->grants->granted($uid, 'deck', 'edit')) {
			throw new ToolFailure(DeckRefusalMessages::errorNotAllowed());
		}

		$comment = null;
		try {
			$comment = $this->comments->add($uid, $arguments['cardId'], $arguments['message']);
		} catch (\Throwable $e) {
			$this->logger->warning('MCP Deck comment write or read-back raised ({exception})', ['exception' => $e::class]);
		}
		$summary = $comment === null
			? Translator::t('The Deck comment could not be verified. Read the card comments before requesting another approval.')
			: Translator::t('Comment added to the Deck card.');
		return ToolResult::structured($summary, [
			'confirmed' => $comment !== null,
			'boardId' => $arguments['boardId'],
			'cardId' => $arguments['cardId'],
			'comment' => $comment,
		]);
	}
}
