<?php


namespace CortexPE\Commando\args\selector;


use pocketmine\command\CommandSender;

abstract class BaseSelector {
	abstract public function getChar(): string;

	abstract public function getTargets(CommandSender $sender, array $args): array;
}