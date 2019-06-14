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


use function array_map;
use function array_merge;
use function array_replace;
use CortexPE\Commando\exception\HookAlreadyRegistered;
use CortexPE\Commando\store\SoftEnumStore;
use CortexPE\Commando\traits\IArgumentable;
use function in_array;
use pocketmine\command\CommandMap;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\CommandData;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use function array_unique;
use function array_unshift;
use function array_values;
use function ucfirst;

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
					$overloadList = self::generateOverloadList($cmd);
					if(!empty(($mySubs = $cmd->getSubCommands()))){
						/** @var BaseSubCommand[] $mySubs */
						$mySubs = array_unique($mySubs, SORT_REGULAR);
						foreach($mySubs as $label => $mySub){
							if($mySub->testPermissionSilent($p)) {
								$subParam = new CommandParameter();
								$subParam->paramName = $commandName . $label;
								$subParam->enum = new CommandEnum();
								$subParam->enum->enumName = $subParam->paramName . "-enum";
								$subParam->enum->enumValues = [$label];
								// bind argument to overload list
								foreach(self::generateOverloadList($mySub) as $k => $params){
									$overloadList[] = array_merge([$subParam], $params);
								}
							}
						}
					}
					$pk->commandData[$commandName]->overloads = $overloadList;
				}
			}
			$pk->softEnums = SoftEnumStore::getEnums();
		}
	}

	/**
	 * @param IArgumentable $argumentable
	 *
	 * @return CommandParameter[][]
	 */
	private static function generateOverloadList(IArgumentable $argumentable): array {
		$overloads = [];
		foreach($argumentable->getArgumentList() as $pos => $converters) {
			foreach($converters as $i => $argument) {
				$overloads[$i][$pos] = $argument->getNetworkParameterData();
			}
		}
		if(empty($overloads)){
			return [];
		}
		$base = $overloads[0];
		$actualOverloads = [$base];
		foreach($overloads as $i => $overload){
			$actualOverloads[$i] = array_replace($base, $overload);
		}

		return $actualOverloads;
	}
}