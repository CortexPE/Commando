<?php

/***
 *    ___                                          _
 *   / __\___  _ __ ___  _ __ ___   __ _ _ __   __| | ___
 *  / /  / _ \| '_ ` _ \| '_ ` _ \ / _` | '_ \ / _` |/ _ \
 * / /__| (_) | | | | | | | | | | | (_| | | | | (_| | (_) |
 * \____/\___/|_| |_| |_|_| |_| |_|\__,_|_| |_|\__,_|\___/
 *
 * Commando - A Command Framework virion for PocketMine-MP
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * Written by @CortexPE <https://CortexPE.xyz>
 *
 */
declare(strict_types=1);

namespace CortexPE\Commando\args;


use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use function array_keys;
use function array_map;
use function implode;
use function preg_match;
use function strtolower;

abstract class StringEnumArgument extends BaseArgument {

	protected const VALUES = [];

	public function __construct(string $name, bool $optional = false) {
		parent::__construct($name, $optional);

		$this->parameterData->enum = new CommandEnum($this->getEnumName(), $this->getEnumValues());
	}

	public function getNetworkType(): int {
		// this will be disregarded by PM anyways because this will be considered as a string enum
		return -1;
	}

	public function canParse(string $testString, CommandSender $sender): bool {
		return (bool)preg_match(
			"/^(" . implode("|", array_map("\\strtolower", $this->getEnumValues())) . ")$/iu",
			$testString
		);
	}

	public abstract function getEnumName(): string;

	public function getValue(string $string) {
		return static::VALUES[strtolower($string)];
	}

	public function getEnumValues(): array {
		return array_keys(static::VALUES);
	}
}
