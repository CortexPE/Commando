<?php


namespace CortexPE\Commando\args\selector;


use pocketmine\command\CommandSender;

class AllPlayersSelector extends BaseSelector {
	public function getChar(): string {
		return "a";
	}

	public function getTargets(CommandSender $sender, array $args): array {
		return $sender->getServer()->getOnlinePlayers();
	}
}