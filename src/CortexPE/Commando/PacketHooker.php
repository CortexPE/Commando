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
			/** @var BaseSubCommand[] $subCommands */
			$subCommands = [];
			foreach($pk->commandData as $commandName => $commandData) {
				$cmd = $this->map->getCommand($commandName);
				if($cmd instanceof BaseCommand) {
					if(!empty(($mySubs = $cmd->getSubCommands()))){
						foreach($mySubs as $mySub){
							if($mySub->testPermissionSilent($p)) {
								$subCommands[] = $mySub;
							}
						}
						$subCommands = array_merge($subCommands, $mySubs);
					}
					$pk->commandData[$commandName]->overloads = self::generateOverloadList($cmd);
				}
			}
			foreach($subCommands as $subCommand){
				$k = ($parName = $subCommand->getParent()->getName()) . " " . ($scName = $subCommand->getName());
				$pk->commandData[$k] = $data = new CommandData();
				$data->commandName = $k;
				$data->commandDescription = $subCommand->getDescription();
				$data->flags = $data->permission = 0;
				if(!empty(($aliases = $subCommand->getAliases()))){
					array_unshift($aliases, $scName);
					$data->aliases = new CommandEnum();
					$data->aliases->enumName = ucfirst($k) . "Aliases";
					$data->aliases->enumValues = array_map(function(string $alias) use ($parName) :string{
						return $parName . " " . $alias;
					}, $aliases);
				}
				$data->overloads = self::generateOverloadList($subCommand);
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
		$params = [];
		foreach($argumentable->getArgumentList() as $pos => $converters) {
			foreach($converters as $i => $argument) {
				$params[$i][$pos] = $argument->getNetworkParameterData();
			}
		}

		return $params;
	}
}