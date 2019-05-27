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

namespace CortexPE\Commando;


use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\exception\HookAlreadyRegistered;
use CortexPE\Commando\traits\IArgumentable;
use pocketmine\command\CommandMap;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\CommandData;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use function array_unique;
use function ksort;

class PacketHooker implements Listener {
	/** @var bool */
	private static $isRegistered = false;
	/** @var CommandMap */
	protected $map;

	public function __construct() {
		$this->map = Server::getInstance()->getCommandMap();
	}

	public static function isRegistered(): bool {
		return self::$isRegistered;
	}

	public static function register(Plugin $registrant): void {
		if(self::$isRegistered) {
			throw new HookAlreadyRegistered("Event listener is already registered by another plugin.");
		}
		$registrant->getServer()->getPluginManager()->registerEvents(new PacketHooker(), $registrant);
	}

	/**
	 * @param DataPacketSendEvent $ev
	 *
	 * @priority        LOWEST
	 * @ignoreCancelled true
	 */
	public function onPacketSend(DataPacketSendEvent $ev): void {
		$pk = $ev->getPacket();
		if($pk instanceof AvailableCommandsPacket) {
			$p = $ev->getPlayer();
			foreach($pk->commandData as $commandName => $commandData) {
				$cmd = $this->map->getCommand($commandName);
				if($cmd instanceof BaseCommand) {
					$data = clone $commandData;
					$overloadIndex = 0;
					self::indexArgumentable($cmd, $p, $overloadIndex, $data);
					//var_dump($data->overloads);
					$pk->commandData[$commandName] = $data;
				}
			}
		}
	}

	/**
	 * @param BaseArgument|BaseSubCommand $cmdParam
	 *
	 * @return CommandParameter
	 */
	private static function generateParameter($cmdParam): CommandParameter {
		$param = new CommandParameter();
		$param->paramName = $cmdParam->getName();
		$param->paramType = AvailableCommandsPacket::ARG_FLAG_VALID;
		if($cmdParam instanceof BaseArgument) {
			$param->paramType |= $cmdParam->getNetworkType();
			$param->isOptional = $cmdParam->isOptional();
		} elseif($cmdParam instanceof BaseSubCommand) {
			$param->paramType |= AvailableCommandsPacket::ARG_TYPE_STRING; // this'll do for now

			// sub-commands are always optional, as the developer might implement help messages or other arguments
			// on it's parent's (BaseCommand) onRun
			$param->isOptional = true;
		}

		// todo: "postfix"? wtf mojang
		// todo: "enums"? & sub-command aliases
		return $param;
	}

	private static function generateParameters(IArgumentable $argumentable): array {
		/** @var CommandParameter[] $parameters */
		$parameters = [];
		foreach($argumentable->getArgumentList() as $pos => $arguments) {
			foreach($arguments as $key => $argument) {
				$parameters[] = $param = self::generateParameter($argument);
			}
		}
		if($argumentable instanceof BaseCommand) {
			$subCommands = array_unique($argumentable->getSubCommands(), SORT_REGULAR);
			/** @var BaseSubCommand $subCommand */
			foreach($subCommands as $subCommand) {
				$parameters[] = $param = self::generateParameter($subCommand);
			}
		}

		return $parameters;
	}

	private static function indexArgumentable(
		IArgumentable $argumentable,
		CommandSender $sender,
		int &$overloadOffset,
		CommandData &$commandData,
		int $posOffset = 0
	): void {
		$params = self::generateParameters($argumentable);
		foreach($params as $param) {
			$commandData->overloads[$overloadOffset][$posOffset] = $param;
			if($argumentable instanceof BaseSubCommand) {
				// show what our overload came from
				$commandData->overloads[$overloadOffset][0] = self::generateParameter($argumentable);
			}
			$overloadOffset++;
		}
		foreach($commandData->overloads as $overloadIndex => $parameters) {
			$arr = $parameters;
			ksort($arr);
			$commandData->overloads[$overloadIndex] = $arr;
		}
		if($argumentable instanceof BaseCommand) {
			$subCommands = array_unique($argumentable->getSubCommands(), SORT_REGULAR);
			foreach($subCommands as $subCommand) {
				self::indexArgumentable($subCommand, $sender, $overloadOffset, $commandData, 1);
			}
		}
	}
}