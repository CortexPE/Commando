<?php


namespace CortexPE\Commando\args\selector;


use pocketmine\command\CommandSender;

class RandomPlayerSelector extends BaseSelector {
	public function getChar(): string {
		return "r";
	}

	public function getTargets(CommandSender $sender, array $args): array {
		$players = $sender->getServer()->getOnlinePlayers();

		return [
			$players[array_rand($players)]
		];
	}
}