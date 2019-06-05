<?php


namespace CortexPE\Commando\args\selector;


use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\Player;

class PlayerSelector extends BaseSelector {
	public function getChar(): string {
		return "p";
	}

	public function getTargets(CommandSender $sender, array $args): array {
		if($sender instanceof Position) {
			return [
				$sender->getLevel()->getNearestEntity(
					$sender, PHP_INT_MAX, Player::class, true
				)
			];
		}

		return [];
	}
}