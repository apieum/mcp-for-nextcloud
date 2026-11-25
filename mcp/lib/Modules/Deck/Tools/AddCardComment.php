<?php
/*
 * SPDX-FileCopyrightText: 2026 Sintax (Jhonatan Jaworski) <contato@sintax.tech>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace OCA\Mcp\Modules\Deck\Tools;

use OCA\Mcp\Mcp\Confirmation\PlanState;
use OCA\Mcp\Mcp\Tools\{ToolContext, ToolDefinition, WriteTool};
use OCA\Mcp\Modules\Deck\Commenting\{AddCardCommentHandler, AddCardCommentPreview};
use OCA\Mcp\Modules\Deck\Presentation\{DeckRefusalMessages, DeckSchema};

/** Publishes one authenticated comment on a Deck card. */
final class AddCardComment implements WriteTool {
	public const TOOL = 'deck_add_card_comment';

	public function __construct(private AddCardCommentHandler $comments, private AddCardCommentPreview $plans) {
	}

	public static function definition(): ToolDefinition {
		return new ToolDefinition(self::TOOL, 'deck', 'edit',
			'Adds a comment to one Deck card as the authenticated Nextcloud account. Requires deck.edit and native Deck read access. Check the comment receipt before requesting another approval.',
			DeckSchema::schema([
				'boardId' => DeckSchema::integer('ID of the board the card must belong to.', 1),
				'cardId' => DeckSchema::integer(DeckRefusalMessages::PARAM_CARD_ID, 1),
				'message' => DeckSchema::string('Exact comment text. Mentions may notify people.', 1, 1000),
				'expectedActorId' => DeckSchema::string('Optional assertion of the authenticated Nextcloud account ID. A mismatch refuses the call.', 1, 255),
				'confirm_shared' => DeckSchema::confirmShared(),
				PlanState::ARGUMENT => PlanState::property(),
			], ['boardId', 'cardId', 'message']), 'deck');
	}

	/** @param array<string, mixed> $arguments @return array<string, mixed> Read-only signed preview. */
	public function preview(array $arguments, ToolContext $context): array {
		return $this->plans->plan($arguments, $context->userId);
	}

	/** @param array<string, mixed> $arguments @return array<string, mixed> MCP response with the observed effect. */
	public function execute(array $arguments, ToolContext $context): array {
		return $this->comments->handle($arguments, $context->userId);
	}
}
