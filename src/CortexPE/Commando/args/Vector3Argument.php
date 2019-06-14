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
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use function count;
use function explode;
use function preg_match;
use function substr;

class Vector3Argument extends BaseArgument {
	public function getNetworkType(): int {
		return AvailableCommandsPacket::ARG_TYPE_POSITION;
	}

	public function getTypeName(): string {
		return "x y z";
	}

	public function canParse(string $testString, CommandSender $sender): bool {
		$coords = explode(" ", $testString);
		if(count($coords) === 3) {
			foreach($coords as $coord) {
				if(!$this->isValidCoordinate($coord, $sender instanceof Vector3)) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	public function isValidCoordinate(string $coordinate, bool $locatable): bool {
		return (bool)preg_match("/^(?:" . ($locatable ? "(?:~-|~\+)?" : "") . "-?(?:\d+|\d*\.\d+))" . ($locatable ? "|~" : "") . "$/", $coordinate);
	}

	public function parse(string $argument, CommandSender $sender) {
		$coords = explode(" ", $argument);
		$vals = [];
		foreach($coords as $k => $coord){
			$offset = 0;
			// if it's locatable and starts with ~- or ~+
			if($sender instanceof Vector3 && preg_match("/^(?:~-|~\+)|~/", $coord)){
				// this will work with -n, +n and "" due to typecast later
				$offset = substr($coord, 1);

				// replace base coordinate with actual entity coordinates
				switch($k){
					case 0:
						$coord = $sender->x;
						break;
					case 1:
						$coord = $sender->y;
						break;
					case 2:
						$coord = $sender->z;
						break;
				}
			}
			$vals[] = (float)$coord + (float)$offset;
		}
		return new Vector3(...$vals);
	}

	public function getSpanLength(): int {
		return 3;
	}
}
