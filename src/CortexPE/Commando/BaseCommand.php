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

use CortexPE\Commando\constraint\BaseConstraint;
use CortexPE\Commando\exception\InvalidErrorCode;
use CortexPE\Commando\traits\ArgumentableTrait;
use CortexPE\Commando\traits\IArgumentable;
use InvalidArgumentException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;
use function array_shift;
use function array_unique;
use function array_unshift;
use function count;
use function dechex;
use function str_replace;

abstract class BaseCommand extends Command implements IArgumentable, IRunnable, PluginOwned {
	use ArgumentableTrait;

	public const ERR_INVALID_ARG_VALUE = 0x01;
	public const ERR_TOO_MANY_ARGUMENTS = 0x02;
	public const ERR_INSUFFICIENT_ARGUMENTS = 0x03;
	public const ERR_NO_ARGUMENTS = 0x04;
	public const ERR_INVALID_ARGUMENTS = 0x05;

	/** @var string[] */
	protected $errorMessages = [
		self::ERR_INVALID_ARG_VALUE => TextFormat::RED . "Invalid value '{value}' for argument #{position}. Expecting: {expected}.",
		self::ERR_TOO_MANY_ARGUMENTS => TextFormat::RED . "Too many arguments given.",
		self::ERR_INSUFFICIENT_ARGUMENTS => TextFormat::RED . "Insufficient number of arguments given.",
		self::ERR_NO_ARGUMENTS => TextFormat::RED . "No arguments are required for this command.",
		self::ERR_INVALID_ARGUMENTS => TextFormat::RED . "Invalid arguments supplied.",
	];

	/** @var CommandSender */
	protected CommandSender $currentSender;

	/** @var BaseSubCommand[] */
	private array $subCommands = [];

	/** @var BaseConstraint[] */
	private array $constraints = [];

	/** @var Plugin */
	protected Plugin $plugin;

	public function __construct(
		Plugin $plugin,
		string $name,
		Translatable|string $description = "",
		array $aliases = []
	) {
		$this->plugin = $plugin;
		parent::__construct($name, $description, null, $aliases);

		$this->prepare();

		$this->usageMessage = $this->generateUsageMessage();
	}

	public function getOwningPlugin(): Plugin {
		return $this->plugin;
	}

	final public function execute(CommandSender $sender, string $commandLabel, array $args){
		$this->currentSender = $sender;
		if(!$this->testPermission($sender)){
			return;
		}
		/** @var BaseCommand|BaseSubCommand $cmd */
		$cmd = $this;
		$passArgs = [];
		if(count($args) > 0){
			if(isset($this->subCommands[($label = $args[0])])){
				array_shift($args);
				$this->subCommands[$label]->execute($sender, $label, $args);
				return;
			}

			$passArgs = $this->attemptArgumentParsing($cmd, $args);
		} elseif($this->hasRequiredArguments()){
			$this->sendError(self::ERR_INSUFFICIENT_ARGUMENTS);
			return;
		}
		if($passArgs !== null) {
			foreach ($cmd->getConstraints() as $constraint){
				if(!$constraint->test($sender, $commandLabel, $passArgs)){
					$constraint->onFailure($sender, $commandLabel, $passArgs);
					return;
				}
			}
			$cmd->onRun($sender, $commandLabel, $passArgs);
		}
	}

	/**
	 * @param ArgumentableTrait $ctx
	 * @param array             $args
	 *
	 * @return array|null
	 */
	private function attemptArgumentParsing($ctx, array $args): ?array {
		$dat = $ctx->parseArguments($args, $this->currentSender);
		if(!empty(($errors = $dat["errors"]))) {
			foreach($errors as $error) {
				$this->sendError($error["code"], $error["data"]);
			}

			return null;
		}

		return $dat["arguments"];
	}

	abstract public function onRun(CommandSender $sender, string $aliasUsed, array $args): void;

	protected function sendUsage(): void {
		$this->currentSender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
	}

	public function sendError(int $errorCode, array $args = []): void {
		$str = (string)$this->errorMessages[$errorCode];
		foreach($args as $item => $value) {
			$str = str_replace('{' . $item . '}', (string) $value, $str);
		}
		$this->currentSender->sendMessage($str);
		$this->sendUsage();
	}

	public function setErrorFormat(int $errorCode, string $format): void {
		if(!isset($this->errorMessages[$errorCode])) {
			throw new InvalidErrorCode("Invalid error code 0x" . dechex($errorCode));
		}
		$this->errorMessages[$errorCode] = $format;
	}

	public function setErrorFormats(array $errorFormats): void {
		foreach($errorFormats as $errorCode => $format) {
			$this->setErrorFormat($errorCode, $format);
		}
	}

	public function registerSubCommand(BaseSubCommand $subCommand): void {
		$keys = $subCommand->getAliases();
		array_unshift($keys, $subCommand->getName());
		$keys = array_unique($keys);
		foreach($keys as $key) {
			if(!isset($this->subCommands[$key])) {
				$subCommand->setParent($this);
				$this->subCommands[$key] = $subCommand;
			} else {
				throw new InvalidArgumentException("SubCommand with same name / alias for '$key' already exists");
			}
		}
	}

	/**
	 * @return BaseSubCommand[]
	 */
	public function getSubCommands(): array {
		return $this->subCommands;
	}

	public function addConstraint(BaseConstraint $constraint) : void {
		$this->constraints[] = $constraint;
	}

	/**
	 * @return BaseConstraint[]
	 */
	public function getConstraints(): array {
		return $this->constraints;
	}

	public function getUsageMessage(): string {
		return $this->getUsage();
	}

	public function setCurrentSender(CommandSender $sender): void{
		$this->currentSender = $sender;
	}
}