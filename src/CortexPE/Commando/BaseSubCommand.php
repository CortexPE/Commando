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
use pocketmine\permission\PermissionManager;
use pocketmine\plugin\Plugin;
use function explode;

abstract class BaseSubCommand implements IArgumentable, IRunnable {
	use ArgumentableTrait;
	/** @var string */
	private string $name;
	/** @var string[] */
	private array $aliases;
	/** @var string */
	private string $description;
	/** @var string */
	protected string $usageMessage;
	/** @var string[] */
	private array $permission = [];
	/** @var CommandSender */
	protected CommandSender $currentSender;
	/** @var BaseCommand */
	protected BaseCommand $parent;
	/** @var BaseConstraint[] */
	private array $constraints = [];

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
	 * @return string[]
	 */
	public function getPermissions(): array {
		return $this->permission;
	}

	/**
	 * @param string[] $permissions
	 */
	public function setPermissions(array $permissions) : void{
		$permissionManager = PermissionManager::getInstance();
		foreach($permissions as $perm){
			if($permissionManager->getPermission($perm) === null){
				throw new \InvalidArgumentException("Cannot use non-existing permission \"$perm\"");
			}
		}
		$this->permission = $permissions;
	}

	public function setPermission(?string $permission) : void{
		$this->setPermissions($permission === null ? [] : explode(";", $permission));
	}

	public function testPermissionSilent(CommandSender $target, ?string $permission = null) : bool{
		$list = $permission !== null ? [$permission] : $this->permission;
		foreach($list as $p){
			if($target->hasPermission($p)){
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
		$this->currentSender->sendMessage("/{$this->parent->getName()} $this->usageMessage");
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
	public function getOwningPlugin(): Plugin {
		return $this->parent->getOwningPlugin();
	}
}