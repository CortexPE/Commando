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
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\Player;
use pocketmine\Server;

class TargetArgument extends BaseArgument {
	/** @var Server */
	private $server;

	public function __construct(string $name, bool $optional) {
		parent::__construct($name, $optional);
		$this->server = Server::getInstance();
	}

	public function getNetworkType() : int {
		return AvailableCommandsPacket::ARG_TYPE_TARGET;
	}

	public function getTypeName() : string {
		return "target";
	}

	public function canParse(string $testString, CommandSender $sender) : bool {
		return Player::isValidUserName($testString);
	}

	public function parse(string $argument, CommandSender $sender) {
		// TODO: handle @a @e @p @r @s @c @v
		$player = $this->server->getPlayer($argument) ?? $this->server->getOfflinePlayer($argument);
		return $player->getName();
	}
}
