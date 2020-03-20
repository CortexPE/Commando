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


use CortexPE\Commando\exception\HookAlreadyRegistered;
use CortexPE\Commando\store\SoftEnumStore;
use CortexPE\Commando\traits\IArgumentable;
use pocketmine\command\CommandMap;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use function array_unique;
use function array_unshift;
use function array_values;

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
					foreach($cmd->getConstraints() as $constraint){
						if(!$constraint->isVisibleTo($p)){
							continue 2;
						}
					}
					$pk->commandData[$commandName]->overloads = self::generateOverloads($p, $cmd);
				}
			}
			$pk->softEnums = SoftEnumStore::getEnums();
		}
	}

	/**
	 * @param CommandSender $cs
	 * @param BaseCommand $command
	 *
	 * @return CommandParameter[][]
	 */
	private static function generateOverloads(CommandSender $cs, BaseCommand $command): array {
		$overloads = [];

		$scEnum = new CommandEnum();
		$scEnum->enumName = $command->getName() . "SubCommands";

		foreach($command->getSubCommands() as $label => $subCommand) {
			if(!$subCommand->testPermissionSilent($cs) || $subCommand->getName() !== $label){ // hide aliases
				continue;
			}
			foreach($subCommand->getConstraints() as $constraint){
				if(!$constraint->isVisibleTo($cs)){
					continue 2;
				}
			}
			$scParam = new CommandParameter();
			$scParam->paramName = $label;
			$scParam->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_FLAG_ENUM;
			$scParam->isOptional = false;
			$scParam->enum = new CommandEnum();
			$scParam->enum->enumName = $label;
			$scParam->enum->enumValues = [$label];

			// it looks uglier imho
			//$scParam->enum = $scEnum;

			$scEnum->enumValues[] = $label;

			$overloadList = self::generateOverloadList($subCommand);
			if(!empty($overloadList)){
				foreach($overloadList as $overload) {
					array_unshift($overload, $scParam);
					$overloads[] = $overload;
				}
			} else {
				$overloads[] = [$scParam];
			}
		}

		foreach(self::generateOverloadList($command) as $overload) {
			$overloads[] = $overload;
		}

		return $overloads;
	}

	/**
	 * @param IArgumentable $argumentable
	 *
	 * @return CommandParameter[][]
	 */
	private static function generateOverloadList(IArgumentable $argumentable): array {
		$input = $argumentable->getArgumentList();
		$combinations = [];
		$outputLength = array_product(array_map("count", $input));
		$indexes = [];
		foreach($input as $k => $charList){
			$indexes[$k] = 0;
		}
		do {
			/** @var CommandParameter[] $set */
			$set = [];
			foreach($indexes as $k => $index){
				$param = $set[$k] = clone $input[$k][$index]->getNetworkParameterData();

				if(isset($param->enum) && $param->enum instanceof CommandEnum){
					$param->enum->enumName = "enum#" . spl_object_id($param->enum);
				}
			}
			$combinations[] = $set;

			foreach($indexes as $k => $v){
				$indexes[$k]++;
				$lim = count($input[$k]);
				if($indexes[$k] >= $lim){
					$indexes[$k] = 0;
					continue;
				}
				break;
			}
		} while(count($combinations) !== $outputLength);

		return $combinations;
	}
}