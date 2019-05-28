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
use pocketmine\network\mcpe\protocol\types\CommandParameter;

abstract class BaseArgument {
	/** @var string */
	protected $name;
	/** @var bool */
	protected $optional = false;
	/** @var CommandParameter */
	protected $parameterData;

	public function __construct(string $name, bool $optional = false) {
		$this->name = $name;
		$this->optional = $optional;

		$this->parameterData = new CommandParameter();
		$this->parameterData->paramName = $name;
		$this->parameterData->paramType = AvailableCommandsPacket::ARG_FLAG_VALID;
		$this->parameterData->paramType |= $this->getNetworkType();
		$this->parameterData->isOptional = $this->isOptional();
	}

	abstract public function getNetworkType(): int;

	/**
	 * @param string            $testString
	 * @param CommandSender     $sender
	 *
	 * @return bool
	 */
	abstract public function canParse(string $testString, CommandSender $sender): bool;

	/**
	 * @param string        $argument
	 * @param CommandSender $sender
	 *
	 * @return mixed
	 */
	abstract public function parse(string $argument, CommandSender $sender);

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return bool
	 */
	public function isOptional(): bool {
		return $this->optional;
	}

	/**
	 * Returns how much command arguments
	 * it takes to build the full argument
	 *
	 * @return int
	 */
	public function getSpanLength(): int {
		return 1;
	}

	abstract public function getTypeName(): string;

	public function getNetworkParameterData():CommandParameter {
		return $this->parameterData;
	}
}
