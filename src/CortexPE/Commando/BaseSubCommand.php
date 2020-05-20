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
use CortexPE\Commando\traits\ArgumentableTrait;
use CortexPE\Commando\traits\IArgumentable;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;
use function explode;

abstract class BaseSubCommand implements IArgumentable, IRunnable {
	use ArgumentableTrait;
	/** @var string */
	private $name;
	/** @var string[] */
	private $aliases = [];
	/** @var string */
	private $description = "";
	/** @var string */
	protected $usageMessage;
	/** @var string|null */
	private $permission = null;
	/** @var CommandSender */
	protected $currentSender;
	/** @var BaseCommand */
	protected $parent;
	/** @var BaseConstraint[] */
	private $constraints = [];

	public function __construct(string $name, string $description = "", array $aliases = []) {
		$this->name = $name;
		$this->description = $description;
		$this->aliases = $aliases;

		$this->prepare();

		$this->usageMessage = $this->generateUsageMessage();
	}

	abstract public function onRun(CommandSender $sender, string $aliasUsed, array $args): void;

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return string[]
	 */
	public function getAliases(): array {
		return $this->aliases;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @return string
	 */
	public function getUsageMessage(): string {
		return $this->usageMessage;
	}

	/**
	 * @return string|null
	 */
	public function getPermission(): ?string {
		return $this->permission;
	}

	/**
	 * @param string $permission
	 */
	public function setPermission(string $permission): void {
		$this->permission = $permission;
	}

	public function testPermissionSilent(CommandSender $sender): bool {
		if(empty($this->permission)) {
			return true;
		}
		foreach(explode(";", $this->permission) as $permission) {
			if($sender->hasPermission($permission)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param CommandSender $currentSender
	 *
	 * @internal Used to pass the current sender from the parent command
	 */
	public function setCurrentSender(CommandSender $currentSender): void {
		$this->currentSender = $currentSender;
	}

	/**
	 * @param BaseCommand $parent
	 *
	 * @internal Used to pass the parent context from the parent command
	 */
	public function setParent(BaseCommand $parent): void {
		$this->parent = $parent;
	}

	public function sendError(int $errorCode, array $args = []): void {
		$this->parent->sendError($errorCode, $args);
	}

	public function sendUsage():void {
		$this->currentSender->sendMessage("/{$this->parent->getName()} {$this->usageMessage}");
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

	/**
	 * @return Plugin
	 */
	public function getPlugin(): Plugin {
		return $this->parent->getPlugin();
	}
}