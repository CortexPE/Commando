<?php


namespace CortexPE\Commando\args\selector;


use pocketmine\command\CommandSender;
use function array_merge;

class AllEntitiesSelector extends BaseSelector {
	public function getChar(): string {
		return "e";
	}

	public function getTargets(CommandSender $sender, array $args): array {
		$entities = [];
		foreach($sender->getServer()->getLevels() as $level) {
			$entities = array_merge($entities, $level->getEntities());
		}

		return $entities;
	}
}