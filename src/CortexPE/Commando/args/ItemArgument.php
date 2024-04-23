<?php

declare(strict_types=1);

namespace CortexPE\Commando\args;

use CortexPE\Commando\args\StringEnumArgument;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\command\CommandSender;

final class ItemArgument extends StringEnumArgument {

	public function getTypeName() : string {
		return "item";
	}

	public function canParse(string $testString, CommandSender $sender) : bool {
		return $this->getValue($testString) instanceof Item;
	}

	public function parse(string $argument, CommandSender $sender) : ?Rank {
		return $this->getValue($argument);
	}

	public function getValue(string $string) : ?Item {
		return StringToItemParser::getInstance()->parse($string);
	}

	public function getEnumValues() : array {
		return StringToItemParser::getInstance()->getKnownAliases();
	}

}
